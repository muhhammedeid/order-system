<?php

namespace Tests\Feature\WhatsApp;

use App\Filament\Resources\WhatsAppCampaigns\Pages\CreateWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\EditWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ListWhatsAppCampaigns;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppDispatch;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
    }

    private function data(int $customers = 2): array
    {
        $product = Product::factory()->create(['price' => 789123]);
        $product->images()->create(['image_path' => 'products/shoe.jpg', 'sort_order' => 0]);
        $audience = Customer::factory()->count($customers)->create(['phone' => '01012345678', 'whatsapp' => null, 'whatsapp_marketing_status' => 'subscribed']);
        $template = WhatsAppTemplate::create(['name' => 'Announcement', 'type' => 'product_announcement', 'active' => true, 'body' => '{{customer_name}} {{product_name}} {{product_code}} {{product_url}}']);

        return ['name' => 'New shoes', 'product_id' => $product->id, 'customer_ids' => $audience->modelKeys(), 'template_ids' => [$template->id]];
    }

    public function test_draft_assigns_all_selected_variations_without_queueing(): void
    {
        $data = $this->data(3);
        $variation = WhatsAppTemplate::create(['name' => 'Second', 'type' => 'product_announcement', 'body' => 'New {{product_name}} {{product_url}}']);
        $data['template_ids'][] = $variation->id;
        $campaign = WhatsAppCampaign::createDraft($data);
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertSame([$data['template_ids'][0], $variation->id, $data['template_ids'][0]], $campaign->recipients()->orderBy('id')->pluck('template_id')->all());
        Queue::assertNothingPushed();
    }

    public function test_missing_consent_rejects_entire_draft(): void
    {
        $data = $this->data();
        Customer::find($data['customer_ids'][1])->update(['whatsapp_marketing_status' => 'unknown']);
        try {
            WhatsAppCampaign::createDraft($data);
            $this->fail('Expected validation failure');
        } catch (ValidationException) {
            $this->assertDatabaseCount('whatsapp_campaigns', 0);
            $this->assertDatabaseCount('whatsapp_campaign_recipients', 0);
        }
    }

    public function test_scheduler_enqueues_one_snapshot_with_url_image_and_no_price(): void
    {
        $campaign = WhatsAppCampaign::createDraft($this->data());
        $campaign->start();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
        $dispatch = WhatsAppDispatch::firstOrFail();
        $this->assertStringContainsString(route('product.show', $campaign->product), $dispatch->body);
        $this->assertStringNotContainsString('789123', $dispatch->body);
        $this->assertSame(url('/storage/products/shoe.jpg'), $dispatch->media_url);
        $this->assertSame($dispatch->id, $campaign->recipients()->first()->dispatch_id);
        $this->assertSame(route('product.show', $campaign->product), $campaign->recipients()->first()->product_url);
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
        $this->travel(31)->seconds();
        // An unprocessed pending recipient is recovered, rather than duplicated.
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
        $dispatch->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => 1]);
        $this->travel(31)->seconds();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 2);
    }

    public function test_pause_resume_and_future_schedule_are_enforced(): void
    {
        $data = $this->data();
        $data['scheduled_at'] = now()->addHour();
        $campaign = WhatsAppCampaign::createDraft($data);
        $campaign->start();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 0);
        $this->travel(61)->minutes();
        $campaign->pause();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 0);
        $campaign->resume();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
    }

    public function test_global_cadence_prevents_separate_campaign_burst(): void
    {
        $first = WhatsAppCampaign::createDraft($this->data(1));
        $first->start();
        $second = WhatsAppCampaign::createDraft($this->dataWithUniqueTemplateName());
        $second->start();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
        WhatsAppDispatch::first()->update(['status' => 'sent']);
        $this->travel(31)->seconds();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 2);
    }

    private function dataWithUniqueTemplateName(): array
    {
        WhatsAppTemplate::first()->update(['name' => 'Previous']);

        return $this->data(1);
    }

    public function test_changed_consent_skips_recipient_before_enqueue(): void
    {
        $data = $this->data(1);
        $campaign = WhatsAppCampaign::createDraft($data);
        $campaign->start();
        Customer::find($data['customer_ids'][0])->update(['whatsapp_marketing_status' => 'unsubscribed']);
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 0);
        $this->assertSame('skipped', $campaign->recipients()->first()->status);
    }

    public function test_pending_recovery_preserves_original_message_and_url_snapshots(): void
    {
        $campaign = WhatsAppCampaign::createDraft($this->data(1));
        $campaign->start();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $recipient = $campaign->recipients()->first();
        $original = $recipient->dispatch->body;
        $originalUrl = $recipient->product_url;
        $recipient->template->update(['body' => 'Changed message']);
        $campaign->product->update(['name' => 'Changed product', 'slug' => 'changed-product']);
        $this->travel(31)->seconds();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertSame($original, $recipient->dispatch->fresh()->body);
        $this->assertSame($originalUrl, $recipient->fresh()->product_url);
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
    }

    public function test_unknown_or_owner_only_variables_reject_draft_without_partial_data(): void
    {
        $data = $this->data(1);
        WhatsAppTemplate::find($data['template_ids'][0])->update(['body' => '{{admin_order_url}} {{request_price}}']);
        try {
            WhatsAppCampaign::createDraft($data);
            $this->fail('Expected unsafe template validation failure');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_ids', $exception->errors());
            $this->assertDatabaseCount('whatsapp_campaigns', 0);
            $this->assertDatabaseCount('whatsapp_campaign_recipients', 0);
        }
    }

    public function test_terminal_dispatches_are_not_rescheduled_and_campaign_completes(): void
    {
        $campaign = WhatsAppCampaign::createDraft($this->data(1));
        $campaign->start();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $dispatch = WhatsAppDispatch::firstOrFail();
        $dispatch->update(['status' => 'unknown', 'attempts' => 1]);
        $this->travel(31)->seconds();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
        $this->assertSame('unknown', $dispatch->fresh()->status);
    }

    public function test_only_failed_pre_send_dispatch_can_be_retried(): void
    {
        $campaign = WhatsAppCampaign::createDraft($this->data(1));
        $campaign->start();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $recipient = $campaign->recipients()->first();
        $recipient->dispatch->update(['status' => 'failed', 'attempts' => 0, 'failure_reason' => 'provider_unavailable']);
        $recipient->retryBeforeSend();
        $this->assertSame('pending', $recipient->dispatch->fresh()->status);
        $recipient->dispatch->update(['status' => 'unknown', 'attempts' => 1]);
        $this->expectException(ValidationException::class);
        $recipient->retryBeforeSend();
    }

    public function test_arabic_phone_digits_and_avif_image_are_supported(): void
    {
        $data = $this->data(1);
        Customer::findOrFail($data['customer_ids'][0])->update(['phone' => '٠١٠١٢٣٤٥٦٧٨']);
        Product::findOrFail($data['product_id'])->images()->first()->update(['image_path' => 'products/shoe.avif']);
        $campaign = WhatsAppCampaign::createDraft($data);
        $campaign->start();
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $dispatch = WhatsAppDispatch::firstOrFail();
        $this->assertSame('201012345678', PhoneNumber::normalize($dispatch->recipient_phone));
        $this->assertSame('image/avif', $dispatch->media_mimetype);
    }

    public function test_oversized_expanded_template_is_skipped_without_dispatch(): void
    {
        $data = $this->data(1);
        $campaign = WhatsAppCampaign::createDraft($data);
        $campaign->start();
        Product::findOrFail($data['product_id'])->update(['name' => str_repeat('ش', 100)]);
        WhatsAppTemplate::findOrFail($data['template_ids'][0])->update(['body' => str_repeat('{{product_name}}', 100)]);
        $this->artisan('whatsapp:dispatch-campaigns')->assertSuccessful();
        $this->assertDatabaseCount('whatsapp_dispatches', 0);
        $this->assertSame('skipped', $campaign->recipients()->first()->status);
    }

    public function test_active_campaign_cannot_be_edited_through_model(): void
    {
        $data = $this->data();
        $campaign = WhatsAppCampaign::createDraft($data);
        $campaign->updateDraft(array_merge($data, ['name' => 'Edited']));
        $this->assertSame('Edited', $campaign->fresh()->name);
        $campaign->start();
        $this->expectException(ValidationException::class);
        $campaign->updateDraft($data);
    }

    public function test_admin_can_create_view_and_transition_campaign_guest_is_rejected(): void
    {
        $this->get('/admin/whatsapp-campaigns')->assertRedirect('/admin/login');
        $admin = User::factory()->create();
        $data = $this->data();
        Livewire::actingAs($admin)->test(CreateWhatsAppCampaign::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $campaign = WhatsAppCampaign::firstOrFail();
        $this->actingAs($admin)->get("/admin/whatsapp-campaigns/{$campaign->id}")->assertOk();
        Livewire::actingAs($admin)->test(EditWhatsAppCampaign::class, ['record' => $campaign->id])->fillForm(['name' => 'Edited in Admin'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Edited in Admin', $campaign->fresh()->name);
        Livewire::actingAs($admin)->test(ListWhatsAppCampaigns::class)->callTableAction('start', $campaign)->assertHasNoErrors();
        $this->assertSame('running', $campaign->fresh()->status);
        Livewire::actingAs($admin)->test(ListWhatsAppCampaigns::class)->callTableAction('pause', $campaign)->assertHasNoErrors();
        $this->assertSame('paused', $campaign->fresh()->status);
        Livewire::actingAs($admin)->test(ListWhatsAppCampaigns::class)->callTableAction('resume', $campaign)->assertHasNoErrors();
        $this->assertSame('running', $campaign->fresh()->status);
        $this->actingAs($admin)->get("/admin/whatsapp-campaigns/{$campaign->id}/edit")->assertForbidden();
    }
}
