<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Models\Customer;
use App\Models\WhatsAppConversation;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\Outbound\OrderConversations;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class OrderConversationsTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    public function test_it_returns_only_the_order_customers_conversations(): void
    {
        $order = $this->createOrderWithCustomer();
        $otherCustomer = Customer::factory()->create();

        $mine = WhatsAppConversation::create([
            'customer_id' => $order->customer_id,
            'provider_chat_id' => '20100000000@c.us',
        ]);
        WhatsAppConversation::create([
            'customer_id' => $otherCustomer->id,
            'provider_chat_id' => '20111111111@c.us',
        ]);

        $conversations = app(OrderConversations::class)->for($order);

        $this->assertCount(1, $conversations);
        $this->assertTrue($conversations->first()->is($mine));
    }

    public function test_it_returns_all_customer_conversations_for_operator_selection(): void
    {
        $order = $this->createOrderWithCustomer();

        $older = WhatsAppConversation::create([
            'customer_id' => $order->customer_id,
            'provider_chat_id' => '20100000000@c.us',
            'last_message_at' => now()->subDay(),
        ]);
        $newer = WhatsAppConversation::create([
            'customer_id' => $order->customer_id,
            'provider_chat_id' => '214457011683409@lid',
            'last_message_at' => now(),
        ]);

        $conversations = app(OrderConversations::class)->for($order);

        $this->assertCount(2, $conversations);
        $this->assertTrue($conversations->first()->is($newer));
        $this->assertTrue($conversations->last()->is($older));
    }

    public function test_first_contact_prefers_the_whatsapp_field_then_falls_back_to_phone(): void
    {
        $order = $this->createOrderWithCustomer([
            'whatsapp' => '0112347663',
            'phone' => '01000000000',
        ]);

        $this->gateway->numberCheck = NumberCheck::exists('20112347663@c.us', '20112347663');

        app(OrderConversations::class)->firstContact($order);

        $this->assertSame(['0112347663'], $this->gateway->checkedNumbers);

        $this->gateway->checkedNumbers = [];
        $fallback = $this->createOrderWithCustomer([
            'whatsapp' => null,
            'phone' => '01000000000',
        ]);

        $this->gateway->numberCheck = NumberCheck::exists('201000000000@c.us', '201000000000');

        app(OrderConversations::class)->firstContact($fallback);

        $this->assertSame(['01000000000'], $this->gateway->checkedNumbers);
    }

    public function test_first_contact_creates_and_links_a_conversation_for_a_verified_number(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $this->gateway->numberCheck = NumberCheck::exists('20112347663@c.us', '20112347663');

        $conversation = app(OrderConversations::class)->firstContact($order);

        $this->assertNotNull($conversation);
        $this->assertSame('20112347663@c.us', $conversation->provider_chat_id);
        $this->assertSame($order->customer_id, $conversation->customer_id);
        $this->assertSame('20112347663', $conversation->resolved_phone);
        $this->assertSame(1, WhatsAppConversation::count());
    }

    public function test_missing_number_returns_null_without_a_provider_call(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => null, 'phone' => '']);

        $this->assertNull(app(OrderConversations::class)->firstContact($order));
        $this->assertSame([], $this->gateway->checkedNumbers);
        $this->assertSame(0, WhatsAppConversation::count());
    }

    public function test_a_number_that_is_not_on_whatsapp_returns_null_and_creates_nothing(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $this->gateway->numberCheck = NumberCheck::notExists();

        $this->assertNull(app(OrderConversations::class)->firstContact($order));
        $this->assertSame(0, WhatsAppConversation::count());
    }

    public function test_a_provider_failure_throws_and_creates_nothing(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $this->gateway->throwOnCheckNumber = WhatsAppException::unreachable('check number');

        try {
            app(OrderConversations::class)->firstContact($order);
            $this->fail('Expected WhatsAppException was not thrown.');
        } catch (WhatsAppException $exception) {
            $this->assertStringContainsString('unreachable', $exception->getMessage());
        }

        $this->assertSame(0, WhatsAppConversation::count());
    }

    public function test_an_existing_unlinked_conversation_is_reused_and_linked(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $existing = WhatsAppConversation::create(['provider_chat_id' => '20112347663@c.us']);
        $this->gateway->numberCheck = NumberCheck::exists('20112347663@c.us', '20112347663');

        $conversation = app(OrderConversations::class)->firstContact($order);

        $this->assertTrue($conversation->is($existing));
        $this->assertSame($order->customer_id, $conversation->customer_id);
        $this->assertSame(1, WhatsAppConversation::count());
    }

    public function test_a_conversation_linked_to_another_customer_is_never_reassigned(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $otherCustomer = Customer::factory()->create();
        $existing = WhatsAppConversation::create([
            'customer_id' => $otherCustomer->id,
            'provider_chat_id' => '20112347663@c.us',
        ]);
        $this->gateway->numberCheck = NumberCheck::exists('20112347663@c.us', '20112347663');

        try {
            app(OrderConversations::class)->firstContact($order);
            $this->fail('A conversation owned by another customer must be rejected.');
        } catch (WhatsAppException $exception) {
            $this->assertSame('WhatsApp conversation belongs to another customer.', $exception->getMessage());
        }

        $this->assertSame($otherCustomer->id, $existing->fresh()->customer_id);
    }

    public function test_an_existing_resolved_phone_is_not_overwritten(): void
    {
        $order = $this->createOrderWithCustomer(['whatsapp' => '0112347663']);
        $existing = WhatsAppConversation::create([
            'provider_chat_id' => '20112347663@c.us',
            'resolved_phone' => '20100000000',
        ]);
        $this->gateway->numberCheck = NumberCheck::exists('20112347663@c.us', '20112347663');

        $conversation = app(OrderConversations::class)->firstContact($order);

        $this->assertTrue($conversation->is($existing));
        $this->assertSame('20100000000', $conversation->resolved_phone);
    }
}
