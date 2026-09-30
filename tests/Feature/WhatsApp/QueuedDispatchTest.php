<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsAppDispatch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppDispatch;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\Outbound\DispatchQueue;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\TestCase;

class QueuedDispatchTest extends TestCase
{
    use RefreshDatabase;

    private FakeWhatsAppGateway $gateway;

    private mixed $queueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueManager = Queue::getFacadeRoot();
        Queue::fake();
        Cache::flush();
        $this->gateway = new FakeWhatsAppGateway;
        $this->gateway->numberCheck = NumberCheck::exists('201001234567@c.us', '201001234567');
    }

    public function test_duplicate_dispatch_is_claimed_and_sent_only_once(): void
    {
        $dispatch = $this->dispatch();
        $duplicate = app(DispatchQueue::class)->enqueue($dispatch->getAttributes());
        $this->assertSame($dispatch->id, $duplicate->id);
        Queue::assertPushed(SendWhatsAppDispatch::class, fn ($job) => $job->connection === 'database'
            && $job->queue === 'whatsapp-orders' && ! $job->afterCommit);
        $this->assertSame([], $this->gateway->calls);
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertCount(1, $this->gateway->sentTexts);
        $this->assertSame('sent', $dispatch->fresh()->status);
        $this->assertSame(1, $dispatch->fresh()->attempts);
        $this->assertDatabaseCount('whatsapp_messages', 1);
    }

    public function test_resolution_uses_normalized_number_without_rewriting_legacy_values(): void
    {
        $dispatch = $this->dispatch(['recipient_phone' => '+20 (100) 123-4567']);
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame(['201001234567'], $this->gateway->checkedNumbers);
    }

    public function test_database_queue_push_waits_until_business_transaction_commits(): void
    {
        Queue::swap($this->queueManager);
        $dispatch = DB::transaction(function (): WhatsAppDispatch {
            $dispatch = $this->dispatch();
            $this->assertDatabaseCount('jobs', 0);

            return $dispatch;
        });
        $this->assertDatabaseHas('jobs', ['queue' => 'whatsapp-orders']);
        $this->assertSame('pending', $dispatch->fresh()->status);
        $this->assertSame([], $this->gateway->calls);
    }

    public function test_rollback_removes_notification_snapshot_without_creating_database_job(): void
    {
        Queue::swap($this->queueManager);
        try {
            DB::transaction(function (): void {
                $this->dispatch();
                $this->assertDatabaseCount('jobs', 0);
                throw new \RuntimeException('Roll back core business change');
            });
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Roll back core business change', $exception->getMessage());
        }
        $this->assertDatabaseCount('whatsapp_dispatches', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_queue_push_failure_after_commit_does_not_undo_business_transaction_and_is_recoverable(): void
    {
        Log::spy();
        $bus = Bus::getFacadeRoot();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('queue unavailable'));
        $dispatch = DB::transaction(fn () => $this->dispatch());
        $this->assertDatabaseHas('customers', ['id' => $dispatch->customer_id]);
        $this->assertSame('pending', $dispatch->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        Log::shouldHaveReceived('warning')->once()->with('WhatsApp dispatch queue unavailable', [
            'dispatch_id' => $dispatch->id, 'exception' => \RuntimeException::class,
        ]);
        Bus::swap($bus);
        Queue::swap($this->queueManager);
        app(DispatchQueue::class)->recoverPending();
        $this->assertDatabaseHas('jobs', ['queue' => 'whatsapp-orders']);
        $this->assertSame([], $this->gateway->calls);
    }

    public function test_cross_customer_resolution_fails_closed_and_preserves_failure_reason(): void
    {
        $other = Customer::factory()->create();
        WhatsAppConversation::create(['provider_chat_id' => '201001234567@c.us', 'customer_id' => $other->id]);
        $dispatch = $this->dispatch();
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame('failed', $dispatch->fresh()->status);
        $this->assertSame('conversation_customer_mismatch', $dispatch->fresh()->failure_reason);
        $this->assertSame([], $this->gateway->sentTexts);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_pre_send_failure_can_be_retried_without_duplicate_delivery(): void
    {
        $dispatch = $this->dispatch();
        $this->gateway->throwOnCheckNumber = WhatsAppException::unreachable('check');
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame('failed', $dispatch->fresh()->status);
        $this->assertSame(0, $dispatch->fresh()->attempts);
        $this->assertTrue(app(DispatchQueue::class)->retryFailed($dispatch->fresh()));
        $this->gateway->throwOnCheckNumber = null;
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame('sent', $dispatch->fresh()->status);
        $this->assertCount(1, $this->gateway->sentTexts);
    }

    public function test_ambiguous_send_is_recorded_unknown_and_never_retried(): void
    {
        $dispatch = $this->dispatch();
        $this->gateway->throwOnAction = WhatsAppException::unreachable('send');
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame('unknown', $dispatch->fresh()->status);
        $this->assertSame(WhatsAppMessageStatus::Unknown, WhatsAppMessage::firstOrFail()->status);
        $this->assertFalse(app(DispatchQueue::class)->retryFailed($dispatch->fresh()));
        $this->gateway->throwOnAction = null;
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertCount(1, $this->gateway->sentTexts);
    }

    public function test_worker_releases_resolution_failure_before_send_with_bounded_backoff(): void
    {
        $dispatch = $this->dispatch();
        $this->gateway->throwOnCheckNumber = WhatsAppException::unreachable('check');
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(30);
        $job = new SendWhatsAppDispatch($dispatch->id);
        $job->setJob($queueJob);
        $job->handle($this->gateway);
        $this->assertSame('pending', $dispatch->fresh()->status);
        $this->assertSame(0, $dispatch->fresh()->attempts);
        $this->assertSame([], $this->gateway->sentTexts);
    }

    public function test_empty_provider_receipt_is_unknown(): void
    {
        $dispatch = $this->dispatch();
        $this->gateway->sentProviderId = '';
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame('unknown', $dispatch->fresh()->status);
        $this->assertSame('provider_response_ambiguous', $dispatch->fresh()->failure_reason);
        $this->assertSame(WhatsAppMessageStatus::Unknown, WhatsAppMessage::firstOrFail()->status);
    }

    public function test_resolution_failures_are_bounded_independently_of_queue_release_count(): void
    {
        $dispatch = $this->dispatch();
        $this->gateway->throwOnCheckNumber = WhatsAppException::unreachable('check');
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('release')->once()->with(30);
        $queueJob->shouldReceive('release')->once()->with(120);
        $job = new SendWhatsAppDispatch($dispatch->id);
        $job->setJob($queueJob);
        for ($failure = 0; $failure < 3; $failure++) {
            $job->handle($this->gateway);
        }
        $this->assertSame('failed', $dispatch->fresh()->status);
        $this->assertSame(3, $dispatch->fresh()->resolution_attempts);
        $this->assertSame(0, $dispatch->fresh()->attempts);
        $this->assertFalse(app(DispatchQueue::class)->retryFailed($dispatch->fresh()));
        $this->assertSame([], $this->gateway->sentTexts);
    }

    public function test_worker_failure_after_claim_is_unknown_and_not_resendable(): void
    {
        $dispatch = $this->dispatch(['status' => 'processing', 'attempts' => 1]);
        (new SendWhatsAppDispatch($dispatch->id))->failed(new \RuntimeException('interrupted'));
        $this->assertSame('unknown', $dispatch->fresh()->status);
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame([], $this->gateway->calls);
    }

    public function test_scheduler_recovers_pending_orders_and_marks_stale_claims_unknown(): void
    {
        $pending = $this->dispatch();
        $processing = $this->dispatch(['status' => 'processing', 'attempts' => 1]);
        $active = $this->dispatch(['status' => 'processing', 'attempts' => 1]);
        $campaign = $this->dispatch(['kind' => 'campaign']);
        DB::table('whatsapp_dispatches')->where('id', $processing->id)->update(['updated_at' => now()->subMinutes(6)]);
        Queue::fake();
        app(DispatchQueue::class)->recoverPending();
        Queue::assertPushed(SendWhatsAppDispatch::class, 1);
        Queue::assertPushed(SendWhatsAppDispatch::class, fn ($job) => $job->dispatchId === $pending->id);
        $this->assertSame('unknown', $processing->fresh()->status);
        $this->assertSame('processing', $active->fresh()->status);
        $this->assertSame('pending', $campaign->fresh()->status);
        $this->assertSame([], $this->gateway->calls);
    }

    public function test_sent_records_are_unchanged_by_failed_callback_or_retry(): void
    {
        $dispatch = $this->dispatch(['status' => 'sent', 'attempts' => 1, 'provider_message_id' => 'receipt']);
        (new SendWhatsAppDispatch($dispatch->id))->failed(new \RuntimeException('late failure'));
        $this->assertSame('sent', $dispatch->fresh()->status);
        $this->assertFalse(app(DispatchQueue::class)->retryFailed($dispatch->fresh()));
    }

    public function test_paused_campaign_stays_pending_without_provider_calls(): void
    {
        [$campaign, $dispatch] = $this->campaignDispatch();
        $campaign->pause();
        (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        $this->assertSame('pending', $dispatch->fresh()->status);
        $this->assertSame([], $this->gateway->calls);
    }

    public function test_revoked_consent_or_inactive_product_or_template_skips_queued_campaign(): void
    {
        foreach (['consent', 'product', 'template'] as $change) {
            [$campaign, $dispatch] = $this->campaignDispatch();
            match ($change) {
                'consent' => Customer::find($dispatch->customer_id)->update(['whatsapp_marketing_status' => 'unsubscribed']),
                'product' => $campaign->product->update(['active' => false]),
                'template' => WhatsAppTemplate::find($dispatch->template_id)->update(['active' => false]),
            };
            (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
            $this->assertSame('skipped', $dispatch->fresh()->status);
            $this->assertSame('skipped', $campaign->recipients()->first()->status);
        }
        $this->assertSame([], $this->gateway->calls);
    }

    public function test_worker_spaces_campaign_backlog_and_tracks_media_delivery(): void
    {
        [$campaign, $first] = $this->campaignDispatch();
        [, $second] = $this->campaignDispatch();
        $first->update(['media_url' => 'https://example.test/shoe.jpg', 'media_mimetype' => 'image/jpeg']);
        (new SendWhatsAppDispatch($first->id))->handle($this->gateway);
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('release')->times(4)->with(30);
        $secondJob = new SendWhatsAppDispatch($second->id);
        $secondJob->setJob($queueJob);
        for ($release = 0; $release < 4; $release++) {
            $secondJob->handle($this->gateway);
        }
        $this->assertSame(0, $secondJob->tries);
        $this->assertSame(0, $second->fresh()->resolution_attempts);
        $this->assertSame('sent', $first->fresh()->status);
        $this->assertSame(WhatsAppMessageType::Image, WhatsAppMessage::findOrFail($first->fresh()->whatsapp_message_id)->message_type);
        $this->assertSame('sent', $campaign->recipients()->first()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(1, count(array_filter($this->gateway->calls, fn ($call) => $call === 'send_media')));
        $this->travel(31)->seconds();
        $this->gateway->sentProviderId = 'SENT-2';
        $secondJob->handle($this->gateway);
        $this->assertSame('sent', $second->fresh()->status);
    }

    private function campaignDispatch(): array
    {
        $customer = Customer::factory()->create(['whatsapp_marketing_status' => 'subscribed']);
        $template = WhatsAppTemplate::create(['name' => fake()->uuid(), 'type' => 'product_announcement', 'active' => true, 'body' => '{{product_name}}']);
        $campaign = WhatsAppCampaign::createDraft([
            'name' => 'Test', 'product_id' => Product::factory()->create()->id,
            'customer_ids' => [$customer->id], 'template_ids' => [$template->id],
        ]);
        $campaign->start();
        $dispatch = $this->dispatch(['kind' => 'campaign', 'customer_id' => $customer->id, 'template_id' => $template->id]);
        $campaign->recipients()->first()->update(['dispatch_id' => $dispatch->id, 'status' => 'queued']);
        WhatsAppConversation::create(['provider_chat_id' => 'chat-'.$customer->id, 'customer_id' => $customer->id]);

        return [$campaign, $dispatch];
    }

    private function dispatch(array $attributes = []): WhatsAppDispatch
    {
        return app(DispatchQueue::class)->enqueue(array_merge([
            'dedupe_key' => 'test:'.fake()->uuid(),
            'kind' => 'order_customer',
            'customer_id' => Customer::factory()->create()->id,
            'recipient_phone' => '01001234567',
            'body' => 'Operational snapshot',
        ], $attributes));
    }
}
