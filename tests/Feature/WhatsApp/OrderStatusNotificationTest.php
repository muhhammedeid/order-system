<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\OrderStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Filament\Resources\OrderManagement\Pages\ViewOrder;
use App\Jobs\SendWhatsAppDispatch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItemColorQuantity;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppDispatch;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\Order\OrderStatusNotifier;
use App\Support\WhatsApp\WhatsAppException;
use Database\Seeders\WhatsAppOrderTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class OrderStatusNotificationTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);

        $this->seed(WhatsAppOrderTemplatesSeeder::class);
    }

    public function test_placed_order_sends_customer_details_and_an_owner_alert(): void
    {
        Setting::set('owner_whatsapp_number', '201555555555');

        $this->gateway->numberChecks = [
            '201001234567' => NumberCheck::exists('201001234567@c.us', '201001234567'),
            '201555555555' => NumberCheck::exists('201555555555@c.us', '201555555555'),
        ];

        $order = $this->checkoutOrder();

        $customerConversation = WhatsAppConversation::query()
            ->where('customer_id', $order->customer_id)
            ->firstOrFail();

        $customerMessage = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame($customerConversation->id, $customerMessage->conversation_id);
        $this->assertSame(WhatsAppMessageStatus::Sent, $customerMessage->status);
        $this->assertStringContainsString($order->order_number, $customerMessage->body);
        $this->assertStringContainsString('Test Store', $customerMessage->body);
        $this->assertStringContainsString($order->items()->firstOrFail()->product_name, $customerMessage->body);
        $this->assertStringContainsString('فريق العمليات', $customerMessage->body);
        $this->assertStringNotContainsString('450.00', $customerMessage->body);

        $ownerConversation = WhatsAppConversation::query()
            ->where('provider_chat_id', '201555555555@c.us')
            ->firstOrFail();

        $this->assertNull($ownerConversation->customer_id);

        $ownerMessage = WhatsAppMessage::query()
            ->where('conversation_id', $ownerConversation->id)
            ->firstOrFail();

        $this->assertNull($ownerMessage->order_id);
        $this->assertStringContainsString($order->order_number, $ownerMessage->body);
        $this->assertStringContainsString('Test Store', $ownerMessage->body);
        $this->assertStringContainsString('01001234567', $ownerMessage->body);
        $this->assertStringContainsString('/admin/', $ownerMessage->body);
    }

    public function test_placed_order_is_silent_when_the_integration_is_disabled(): void
    {
        Setting::set('owner_whatsapp_number', '201555555555');
        $this->gateway->enabled = false;

        $this->checkoutOrder();

        $this->assertSame([], $this->gateway->calls);
        $this->assertSame(0, WhatsAppConversation::count());
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_unreachable_customer_still_notifies_the_owner(): void
    {
        Setting::set('owner_whatsapp_number', '201555555555');

        $this->gateway->numberChecks = [
            '201001234567' => NumberCheck::notExists(),
            '201555555555' => NumberCheck::exists('201555555555@c.us', '201555555555'),
        ];

        $order = $this->checkoutOrder();

        $this->assertDatabaseCount('whatsapp_conversations', 1);
        $this->assertDatabaseCount('whatsapp_messages', 1);

        $ownerMessage = WhatsAppMessage::query()->firstOrFail();

        $this->assertNull($ownerMessage->order_id);
        $this->assertStringContainsString($order->order_number, $ownerMessage->body);
    }

    public function test_provider_failure_never_blocks_checkout(): void
    {
        Setting::set('owner_whatsapp_number', '201555555555');
        $this->gateway->throwOnCheckNumber = WhatsAppException::unreachable('check number');

        $order = $this->checkoutOrder();

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(0, WhatsAppConversation::count());
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_owner_alert_is_skipped_without_a_configured_owner_number(): void
    {
        $this->gateway->numberChecks = [
            '201001234567' => NumberCheck::exists('201001234567@c.us', '201001234567'),
        ];

        $order = $this->checkoutOrder();

        $this->assertSame(1, WhatsAppMessage::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, WhatsAppMessage::query()->whereNull('order_id')->count());
    }

    public function test_owner_chat_linked_to_a_customer_is_never_used_for_owner_alerts(): void
    {
        Setting::set('owner_whatsapp_number', '201555555555');

        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663'], quantity: 10);
        $this->linkedConversation($order, '20112347663@c.us');

        $otherCustomer = Customer::factory()->create();
        WhatsAppConversation::query()->create([
            'customer_id' => $otherCustomer->id,
            'provider_chat_id' => '201555555555@c.us',
        ]);

        $this->gateway->numberChecks = [
            '201555555555' => NumberCheck::exists('201555555555@c.us', '201555555555'),
        ];

        $result = app(OrderStatusNotifier::class)->orderPlaced($order);
        $this->runPending();

        $this->assertSame([OrderStatusNotifier::PLACED_CUSTOMER, OrderStatusNotifier::PLACED_OWNER], $result->queued);
        $this->assertDatabaseHas('whatsapp_dispatches', ['kind' => 'order_owner', 'status' => 'failed', 'failure_reason' => 'conversation_customer_mismatch']);
        $this->assertSame(1, WhatsAppMessage::count());
    }

    public function test_admin_action_succeeds_and_worker_tracks_ambiguous_send(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663'], quantity: 10);
        $this->linkedConversation($order);
        $this->gateway->throwOnAction = WhatsAppException::unreachable('send');

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('confirm', data: ['admin_notes' => null])
            ->assertHasNoActionErrors();

        $this->runPending();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertDatabaseHas('whatsapp_dispatches', ['order_id' => $order->id, 'status' => 'unknown']);

        $message = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(WhatsAppMessageStatus::Unknown, $message->status);
    }

    public function test_confirm_action_sends_the_confirmed_update(): void
    {
        $order = $this->createOrderWithCustomer(['name' => 'Confirm Co', 'whatsapp' => '0112347663'], quantity: 10);
        $conversation = $this->linkedConversation($order);

        Livewire::actingAs(User::factory()->create())
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('confirm', data: ['admin_notes' => null])
            ->assertHasNoActionErrors();

        $this->runPending();

        $order->refresh();

        $this->assertSame(OrderStatus::Confirmed, $order->status);

        $message = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertStringContainsString('تم تأكيد طلبك رقم '.$order->order_number, $message->body);
    }

    public function test_each_partial_delivery_sends_updated_totals(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663'], ['status' => 'confirmed'], quantity: 10);
        $this->linkedConversation($order);

        $item = $order->items()->firstOrFail();
        $colorRow = OrderItemColorQuantity::query()->where('order_item_id', $item->id)->firstOrFail();
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('recordDelivery', data: [
                'deliveries' => [$colorRow->id => 2],
                'expected' => [$colorRow->id => 0],
            ])
            ->assertHasNoActionErrors();

        $this->runPending();

        $this->assertSame(OrderStatus::PartiallyDelivered, $order->refresh()->status);

        $first = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertStringContainsString('تم التسليم: 2 قطعة', $first->body);
        $this->assertStringContainsString('المتبقي: 8 قطعة', $first->body);

        $this->gateway->sentProviderId = 'SENT-2';

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('recordDelivery', data: [
                'deliveries' => [$colorRow->id => 3],
                'expected' => [$colorRow->id => 2],
            ])
            ->assertHasNoActionErrors();

        $this->runPending();

        $this->assertSame(2, WhatsAppMessage::query()->where('order_id', $order->id)->count());

        $second = WhatsAppMessage::query()
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertStringContainsString('تم التسليم: 5 قطعة', $second->body);
        $this->assertStringContainsString('المتبقي: 5 قطعة', $second->body);
    }

    public function test_deliver_all_sends_the_delivered_update_once(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663'], ['status' => 'confirmed'], quantity: 10);
        $this->linkedConversation($order);

        Livewire::actingAs(User::factory()->create())
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('deliverAll')
            ->assertHasNoActionErrors();

        $this->runPending();

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);

        $message = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertStringContainsString('تم تسليم طلبك رقم '.$order->order_number.' بالكامل', $message->body);
    }

    public function test_reconciliation_without_a_status_change_is_silent(): void
    {
        $order = $this->createOrderWithCustomer(
            ['whatsapp' => '0112347663'],
            ['status' => 'partially_delivered'],
            quantity: 10,
            delivered: 4,
        );
        $this->linkedConversation($order);

        $notifier = app(OrderStatusNotifier::class);

        $result = $notifier->statusChanged($order, OrderStatus::PartiallyDelivered);

        $this->assertSame([], $result->queued);
        $this->assertSame(0, WhatsAppMessage::count());

        $result = $notifier->statusChanged($order, OrderStatus::PartiallyDelivered, deliveryEvent: true);
        $this->runPending();

        $this->assertSame([OrderStatusNotifier::PARTIALLY_DELIVERED_CUSTOMER], $result->queued);
        $this->assertSame(1, WhatsAppMessage::count());
    }

    public function test_cancelled_orders_send_nothing(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663'], quantity: 10);
        $this->linkedConversation($order);

        $order->cancel();
        $order->refresh();

        $result = app(OrderStatusNotifier::class)->statusChanged($order, OrderStatus::New);

        $this->assertSame([], $result->queued);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_unsubscribed_customer_still_receives_operational_updates(): void
    {
        $order = $this->createOrderWithCustomer(
            ['whatsapp' => '0112347663', 'whatsapp_marketing_status' => 'unsubscribed'],
            quantity: 10,
        );
        $this->linkedConversation($order);

        Livewire::actingAs(User::factory()->create())
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('confirm', data: ['admin_notes' => null])
            ->assertHasNoActionErrors();

        $this->runPending();

        $this->assertSame(1, WhatsAppMessage::query()->where('order_id', $order->id)->count());
    }

    public function test_inactive_template_skips_the_update_without_failing_the_action(): void
    {
        WhatsAppTemplate::query()
            ->where('key', OrderStatusNotifier::CONFIRMED_CUSTOMER)
            ->update(['active' => false]);

        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663'], quantity: 10);
        $this->linkedConversation($order);

        Livewire::actingAs(User::factory()->create())
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('confirm', data: ['admin_notes' => null])
            ->assertHasNoActionErrors();

        $this->runPending();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_invalid_order_template_configuration_is_skipped_without_a_provider_failure(): void
    {
        WhatsAppTemplate::query()
            ->where('key', OrderStatusNotifier::CONFIRMED_CUSTOMER)
            ->update(['body' => 'Bad {{product_name}}']);

        $order = $this->createOrderWithCustomer(
            ['whatsapp' => '0112347663'],
            ['status' => 'confirmed'],
            quantity: 10,
        );
        $this->linkedConversation($order);

        $result = app(OrderStatusNotifier::class)->statusChanged($order, OrderStatus::New);

        $this->assertSame([], $result->queued);
        $this->assertSame([], $result->failed);
        $this->assertContains(OrderStatusNotifier::CONFIRMED_CUSTOMER.':template_invalid', $result->skipped);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_bodies_never_contain_prices_notes_or_internal_fields(): void
    {
        Setting::set('owner_whatsapp_number', '201555555555');

        $this->gateway->numberChecks = [
            '0112347663' => NumberCheck::exists('20112347663@c.us', '20112347663'),
            '201555555555' => NumberCheck::exists('201555555555@c.us', '201555555555'),
        ];

        $order = $this->createOrderWithCustomer(
            ['name' => 'Privacy Co', 'whatsapp' => '0112347663'],
            ['admin_notes' => 'INTERNAL_SECRET_NOTE', 'customer_notes' => 'CUSTOMER_SECRET_NOTE'],
            quantity: 10,
        );

        app(OrderStatusNotifier::class)->orderPlaced($order);
        $this->runPending();

        $this->assertSame(2, WhatsAppMessage::count());

        $unitPrice = (string) $order->items()->firstOrFail()->unit_price;

        foreach (WhatsAppMessage::query()->get() as $message) {
            $this->assertStringNotContainsString('INTERNAL_SECRET_NOTE', $message->body);
            $this->assertStringNotContainsString('CUSTOMER_SECRET_NOTE', $message->body);
            $this->assertStringNotContainsString($unitPrice, $message->body);
        }
    }

    public function test_order_templates_are_seeded_idempotently_and_edits_survive(): void
    {
        $this->seed(WhatsAppOrderTemplatesSeeder::class);
        $this->seed(WhatsAppOrderTemplatesSeeder::class);

        $this->assertDatabaseCount('whatsapp_templates', 5);

        $template = WhatsAppTemplate::query()
            ->where('key', OrderStatusNotifier::PLACED_CUSTOMER)
            ->firstOrFail();

        $template->update(['body' => 'EDITED_BODY']);

        $this->seed(WhatsAppOrderTemplatesSeeder::class);

        $this->assertSame('EDITED_BODY', $template->refresh()->body);
    }

    public function test_reseeding_recreates_missing_templates_without_touching_edited_ones(): void
    {
        $edited = WhatsAppTemplate::query()
            ->where('key', OrderStatusNotifier::PLACED_CUSTOMER)
            ->firstOrFail();

        $edited->update(['body' => 'EDITED_BODY']);

        WhatsAppTemplate::query()
            ->where('key', OrderStatusNotifier::DELIVERED_CUSTOMER)
            ->delete();

        $this->assertDatabaseCount('whatsapp_templates', 4);

        $this->seed(WhatsAppOrderTemplatesSeeder::class);

        $this->assertDatabaseCount('whatsapp_templates', 5);
        $this->assertSame('EDITED_BODY', $edited->refresh()->body);

        $recreated = WhatsAppTemplate::query()
            ->where('key', OrderStatusNotifier::DELIVERED_CUSTOMER)
            ->firstOrFail();

        $this->assertSame('Order Delivered - Customer', $recreated->name);
        $this->assertSame('order', $recreated->type->value);
        $this->assertSame(OrderStatusNotifier::DELIVERED_CUSTOMER, $recreated->key);
    }

    private function runPending(): void
    {
        foreach (WhatsAppDispatch::query()->where('status', 'pending')->get() as $dispatch) {
            (new SendWhatsAppDispatch($dispatch->id))->handle($this->gateway);
        }
    }

    private function checkoutOrder(): Order
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create([
                'price_visibility' => 'public',
                'price' => 450,
                'size_enabled' => true,
            ]))
            ->create(['available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);
        $this->post('/checkout', ['name' => 'Test Store', 'phone' => '01001234567'])
            ->assertRedirect();

        $this->assertSame([], $this->gateway->calls);
        $this->runPending();

        return Order::query()->firstOrFail();
    }

    private function linkedConversation(Order $order, string $chatId = '20100000000@c.us'): WhatsAppConversation
    {
        return WhatsAppConversation::query()->create([
            'customer_id' => $order->customer_id,
            'provider_chat_id' => $chatId,
        ]);
    }
}
