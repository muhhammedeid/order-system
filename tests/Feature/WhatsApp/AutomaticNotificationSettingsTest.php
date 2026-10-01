<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\OrderStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Filament\Pages\Settings;
use App\Jobs\SendWhatsAppDispatch;
use App\Models\Setting;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppDispatch;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\Order\OrderStatusNotifier;
use App\Support\WhatsApp\Outbound\DispatchQueue;
use App\Support\WhatsApp\Outbound\MessageSender;
use Database\Seeders\WhatsAppOrderTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class AutomaticNotificationSettingsTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->app->instance(WhatsAppGateway::class, new FakeWhatsAppGateway);
        $this->seed(WhatsAppOrderTemplatesSeeder::class);
        Setting::set('owner_whatsapp_number', '201555555555');
    }

    public function test_absent_settings_preserve_both_automatic_notification_types(): void
    {
        $result = app(OrderStatusNotifier::class)->orderPlaced($this->createOrderWithCustomer());

        $this->assertSame([OrderStatusNotifier::PLACED_CUSTOMER, OrderStatusNotifier::PLACED_OWNER], $result->queued);
        $this->assertDatabaseCount('whatsapp_dispatches', 2);
        Livewire::test(Settings::class)
            ->assertSet('data.whatsapp_customer_notifications_enabled', true)
            ->assertSet('data.whatsapp_manager_notifications_enabled', true);
    }

    public function test_settings_page_persists_independent_switches_and_can_reenable_them(): void
    {
        foreach ([[false, true], [true, false], [true, true]] as [$customer, $manager]) {
            Livewire::test(Settings::class)
                ->set('data.whatsapp_number', '201234567890')
                ->set('data.whatsapp_customer_notifications_enabled', $customer)
                ->set('data.whatsapp_manager_notifications_enabled', $manager)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertSame($customer, Setting::whatsappCustomerNotificationsEnabled());
            $this->assertSame($manager, Setting::whatsappManagerNotificationsEnabled());
            Livewire::test(Settings::class)
                ->assertSet('data.whatsapp_customer_notifications_enabled', $customer)
                ->assertSet('data.whatsapp_manager_notifications_enabled', $manager);
        }
    }

    public function test_customer_switch_skips_placement_and_status_updates_but_queues_manager(): void
    {
        Setting::set('whatsapp_customer_notifications_enabled', '0');
        $order = $this->createOrderWithCustomer();
        $notifier = app(OrderStatusNotifier::class);
        $result = $notifier->orderPlaced($order);
        $this->assertSame([OrderStatusNotifier::PLACED_OWNER], $result->queued);
        $this->assertSame([OrderStatusNotifier::PLACED_CUSTOMER.':automatic_notifications_disabled'], $result->skipped);

        foreach ([OrderStatus::Confirmed, OrderStatus::PartiallyDelivered, OrderStatus::Delivered] as $status) {
            $order->forceFill(['status' => $status])->save();
            $result = $notifier->statusChanged($order, OrderStatus::New);
            $this->assertSame([], $result->queued);
            $this->assertCount(1, $result->skipped);
        }
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
    }

    public function test_manager_switch_skips_manager_but_queues_customer(): void
    {
        Setting::set('whatsapp_manager_notifications_enabled', '0');
        $result = app(OrderStatusNotifier::class)->orderPlaced($this->createOrderWithCustomer());

        $this->assertSame([OrderStatusNotifier::PLACED_CUSTOMER], $result->queued);
        $this->assertSame([OrderStatusNotifier::PLACED_OWNER.':automatic_notifications_disabled'], $result->skipped);
        $this->assertDatabaseCount('whatsapp_dispatches', 1);
    }

    public function test_switches_skip_already_pending_notifications_without_provider_calls_or_deleting_audit(): void
    {
        app(OrderStatusNotifier::class)->orderPlaced($this->createOrderWithCustomer());
        Setting::set('whatsapp_customer_notifications_enabled', '0');
        Setting::set('whatsapp_manager_notifications_enabled', '0');
        $gateway = new FakeWhatsAppGateway;

        foreach (WhatsAppDispatch::all() as $dispatch) {
            (new SendWhatsAppDispatch($dispatch->id))->handle($gateway);
            $this->assertSame('skipped', $dispatch->fresh()->status);
            $this->assertSame('automatic_notifications_disabled', $dispatch->fresh()->failure_reason);
            $this->assertSame(0, $dispatch->fresh()->attempts);
        }
        $this->assertSame([], $gateway->calls);
        $this->assertDatabaseCount('whatsapp_dispatches', 2);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_disabled_customer_notifications_do_not_prevent_pending_manager_or_manual_sends(): void
    {
        $order = $this->createOrderWithCustomer();
        app(OrderStatusNotifier::class)->orderPlaced($order);
        Setting::set('whatsapp_customer_notifications_enabled', '0');
        $owner = WhatsAppConversation::create(['provider_chat_id' => '201555555555@c.us']);
        $customer = WhatsAppConversation::create(['provider_chat_id' => '20100000000@c.us', 'customer_id' => $order->customer_id]);
        $gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $gateway);
        $gateway->numberCheck = NumberCheck::exists($owner->provider_chat_id, '201555555555');
        (new SendWhatsAppDispatch(WhatsAppDispatch::where('kind', 'order_owner')->sole()->id))->handle($gateway);
        Setting::set('whatsapp_manager_notifications_enabled', '0');
        $manual = app(MessageSender::class)->send($customer, 'Manual reply');

        $this->assertSame(WhatsAppMessageStatus::Sent, $manual->fresh()->status);
        $this->assertSame('sent', WhatsAppDispatch::where('kind', 'order_owner')->sole()->status);
        $this->assertCount(2, $gateway->sentTexts);
        $this->assertSame($customer->id, WhatsAppMessage::where('body', 'Manual reply')->sole()->conversation_id);
    }

    public function test_customer_switch_is_checked_again_after_recipient_resolution(): void
    {
        app(OrderStatusNotifier::class)->orderPlaced($this->createOrderWithCustomer());
        $dispatch = WhatsAppDispatch::where('kind', 'order_customer')->sole();
        $gateway = new class extends FakeWhatsAppGateway
        {
            public function checkNumber(string $phone): NumberCheck
            {
                Setting::set('whatsapp_customer_notifications_enabled', '0');

                return NumberCheck::exists('20100000000@c.us', '20100000000');
            }
        };
        (new SendWhatsAppDispatch($dispatch->id))->handle($gateway);

        $this->assertSame('skipped', $dispatch->fresh()->status);
        $this->assertSame('automatic_notifications_disabled', $dispatch->fresh()->failure_reason);
        $this->assertSame([], $gateway->sentTexts);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_stale_disabled_worker_cannot_skip_another_workers_in_flight_send(): void
    {
        $order = $this->createOrderWithCustomer();
        app(OrderStatusNotifier::class)->orderPlaced($order);
        $dispatch = WhatsAppDispatch::where('kind', 'order_customer')->sole();
        Setting::set('whatsapp_customer_notifications_enabled', '0');
        $conversation = WhatsAppConversation::create(['provider_chat_id' => '20100000000@c.us', 'customer_id' => $order->customer_id]);
        $interleave = true;

        // Another worker claims after this worker reads pending, before it skips.
        DB::listen(function ($query) use ($dispatch, $conversation, &$interleave): void {
            if (! $interleave || ! in_array('whatsapp_customer_notifications_enabled', $query->bindings, true)) {
                return;
            }
            $interleave = false;
            $message = $conversation->messages()->create([
                'direction' => 'outbound', 'message_type' => 'text', 'body' => $dispatch->body,
                'status' => WhatsAppMessageStatus::Pending, 'occurred_at' => now(),
            ]);
            $dispatch->forceFill(['status' => 'processing', 'attempts' => 1, 'whatsapp_message_id' => $message->id])->save();
        });

        $gateway = new FakeWhatsAppGateway;
        $job = new SendWhatsAppDispatch($dispatch->id);
        $job->handle($gateway);
        $this->assertFalse($interleave);
        $this->assertSame('processing', $dispatch->fresh()->status);
        $this->assertNull($dispatch->fresh()->failure_reason);
        $this->assertSame([], $gateway->calls);

        $job->failed(new \RuntimeException('Interrupted after provider send began'));
        $this->assertSame('unknown', $dispatch->fresh()->status);
        $this->assertSame(WhatsAppMessageStatus::Unknown, WhatsAppMessage::findOrFail($dispatch->fresh()->whatsapp_message_id)->status);
    }

    public function test_reenabling_notifications_never_replays_skipped_pending_dispatches(): void
    {
        app(OrderStatusNotifier::class)->orderPlaced($this->createOrderWithCustomer());
        Setting::set('whatsapp_customer_notifications_enabled', '0');
        Setting::set('whatsapp_manager_notifications_enabled', '0');
        $gateway = new FakeWhatsAppGateway;
        foreach (WhatsAppDispatch::all() as $dispatch) {
            (new SendWhatsAppDispatch($dispatch->id))->handle($gateway);
        }
        Setting::set('whatsapp_customer_notifications_enabled', '1');
        Setting::set('whatsapp_manager_notifications_enabled', '1');
        Queue::fake();
        $queue = app(DispatchQueue::class);
        $queue->recoverPending();
        Queue::assertNothingPushed();
        foreach (WhatsAppDispatch::all() as $dispatch) {
            $this->assertFalse($queue->retryFailed($dispatch));
            (new SendWhatsAppDispatch($dispatch->id))->handle($gateway);
            $this->assertSame('skipped', $dispatch->fresh()->status);
            $this->assertSame('automatic_notifications_disabled', $dispatch->fresh()->failure_reason);
            $this->assertSame(0, $dispatch->fresh()->attempts);
        }
        $this->assertSame([], $gateway->calls);
        $this->assertDatabaseCount('whatsapp_dispatches', 2);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }
}
