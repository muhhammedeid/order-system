<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageStatus;
use App\Livewire\OrderWhatsAppPanel;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\Order\OrderMessageTemplates;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class OrderWhatsAppPanelTest extends TestCase
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
    }

    public function test_rendering_the_panel_creates_nothing_and_calls_no_provider(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);

        Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->assertSee('WhatsApp Communication');

        $this->assertSame([], $this->gateway->calls);
        $this->assertSame(0, WhatsAppConversation::count());
    }

    public function test_panel_shows_the_single_conversation_and_only_order_messages(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = $this->conversation($order, '20100000000@c.us');

        $conversation->messages()->create([
            'order_id' => $order->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => 'ORDER_LINKED_MESSAGE',
            'status' => WhatsAppMessageStatus::Sent,
            'occurred_at' => now(),
        ]);
        $conversation->messages()->create([
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'GENERAL_MESSAGE',
            'occurred_at' => now(),
        ]);

        Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->assertSee('ORDER_LINKED_MESSAGE')
            ->assertSee('/admin/whatsapp-conversations/'.$conversation->id)
            ->assertDontSee('GENERAL_MESSAGE');
    }

    public function test_custom_send_persists_the_order_link_and_clears_the_composer(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = $this->conversation($order, '20100000000@c.us');

        Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'Order update')
            ->call('sendCustomMessage')
            ->assertHasNoErrors()
            ->assertSet('messageBody', '');

        $message = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('Order update', $message->body);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame($conversation->id, $message->conversation_id);
    }

    public function test_predefined_message_preview_then_send_and_cancel(): void
    {
        $order = $this->createOrderWithCustomer([], ['status' => 'confirmed'], quantity: 10);
        $this->conversation($order, '20100000000@c.us');

        $expected = OrderMessageTemplates::for($order->load('items', 'customer'))[0]['body'];

        $component = Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id]);

        $component->call('previewTemplate', OrderMessageTemplates::ORDER_CONFIRMED)
            ->assertSet('previewBody', $expected);

        $component->call('cancelTemplatePreview')->assertSet('previewBody', null);
        $this->assertSame(0, WhatsAppMessage::query()->where('order_id', $order->id)->count());

        $component->call('previewTemplate', OrderMessageTemplates::ORDER_CONFIRMED)
            ->call('sendTemplate')
            ->assertSet('previewBody', null);

        $message = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame($expected, $message->body);
        $this->assertSame($order->id, $message->order_id);
    }

    public function test_multiple_conversations_require_explicit_selection(): void
    {
        $order = $this->createOrderWithCustomer();
        $this->conversation($order, '20100000000@c.us');
        $second = $this->conversation($order, '214457011683409@lid');

        $component = Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'Hi')
            ->call('sendCustomMessage');

        $this->assertSame(0, WhatsAppMessage::count());
        $this->assertNotContains('send_text', $this->gateway->calls);
        $component->assertNotified(__('admin.whatsapp.order.select_conversation'));

        $component->set('selectedConversationId', $second->id)->call('sendCustomMessage');

        $message = WhatsAppMessage::query()->firstOrFail();

        $this->assertSame($second->id, $message->conversation_id);
    }

    public function test_first_contact_sends_and_creates_the_conversation_after_verification(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $this->gateway->numberCheck = NumberCheck::exists('20112347663@c.us', '20112347663');

        Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'First contact')
            ->call('sendCustomMessage');

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertSame('20112347663@c.us', $conversation->provider_chat_id);
        $this->assertSame($order->customer_id, $conversation->customer_id);

        $message = WhatsAppMessage::query()->firstOrFail();

        $this->assertSame($order->id, $message->order_id);
        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame(['0112347663'], $this->gateway->checkedNumbers);
    }

    public function test_first_contact_with_a_non_whatsapp_number_blocks_safely(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $this->gateway->numberCheck = NumberCheck::notExists();

        $component = Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'Hi')
            ->call('sendCustomMessage');

        $this->assertSame(0, WhatsAppConversation::count());
        $this->assertSame(0, WhatsAppMessage::count());
        $this->assertNotContains('send_text', $this->gateway->calls);
        $component->assertNotified(__('admin.whatsapp.order.number_invalid'));
    }

    public function test_first_contact_provider_failure_is_not_reported_as_an_invalid_number(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $this->gateway->throwOnCheckNumber = WhatsAppException::unreachable('check number');

        $component = Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'Hi')
            ->call('sendCustomMessage');

        $this->assertSame(0, WhatsAppConversation::count());
        $component->assertNotified(__('admin.whatsapp.order.service_unavailable'));
    }

    public function test_missing_number_blocks_with_a_clear_message(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => null, 'phone' => '']);

        $component = Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'Hi')
            ->call('sendCustomMessage');

        $this->assertSame(0, WhatsAppConversation::count());
        $component->assertNotified(__('admin.whatsapp.order.no_number'));
    }

    public function test_disabled_integration_blocks_sending(): void
    {
        $order = $this->createOrderWithCustomer();
        $this->gateway->enabled = false;

        $component = Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'Hi')
            ->call('sendCustomMessage');

        $this->assertSame(0, WhatsAppMessage::count());
        $this->assertSame([], $this->gateway->calls);
        $component->assertNotified(__('admin.whatsapp.notifications.disabled'));
    }

    public function test_message_bodies_are_rendered_escaped(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = $this->conversation($order, '20100000000@c.us');

        $conversation->messages()->create([
            'order_id' => $order->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => '<script>PANEL_XSS_MARKER</script>',
            'occurred_at' => now(),
        ]);

        Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->assertSee('&lt;script&gt;PANEL_XSS_MARKER&lt;/script&gt;', false)
            ->assertDontSee('<script>PANEL_XSS_MARKER', false);
    }

    public function test_guest_cannot_open_the_order_view(): void
    {
        $order = $this->createOrderWithCustomer();

        $this->get("/admin/order-management/{$order->id}")->assertRedirect('/admin/login');
    }

    public function test_admin_sees_the_panel_on_the_order_view(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->createOrderWithCustomer();

        $this->withSession(['locale' => 'en'])
            ->get("/admin/order-management/{$order->id}")
            ->assertOk()
            ->assertSee('WhatsApp Communication');
    }

    private function conversation(Order $order, string $chatId): WhatsAppConversation
    {
        return WhatsAppConversation::create([
            'customer_id' => $order->customer_id,
            'provider_chat_id' => $chatId,
        ]);
    }
}
