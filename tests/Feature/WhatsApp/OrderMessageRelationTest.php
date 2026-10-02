<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageType;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class OrderMessageRelationTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    public function test_a_message_may_reference_an_order(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = WhatsAppConversation::create(['provider_chat_id' => '20100000000@c.us']);

        $message = $this->outboundMessage($conversation, $order->id);

        $this->assertTrue($message->order->is($order));
        $this->assertSame(1, $order->whatsappMessages()->count());
    }

    public function test_a_message_may_exist_without_an_order(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = WhatsAppConversation::create(['provider_chat_id' => '20100000000@c.us']);

        $message = $this->outboundMessage($conversation, null);

        $this->assertNull($message->order_id);
        $this->assertNull($message->order);
        $this->assertSame(0, $order->whatsappMessages()->count());
    }

    public function test_one_conversation_can_hold_messages_for_multiple_orders(): void
    {
        $first = $this->createOrderWithCustomer();
        $second = $this->createOrderWithCustomer();
        $conversation = WhatsAppConversation::create(['provider_chat_id' => '20100000000@c.us']);

        $this->outboundMessage($conversation, $first->id);
        $this->outboundMessage($conversation, $second->id);
        $this->outboundMessage($conversation, null);

        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame(1, $first->whatsappMessages()->count());
        $this->assertSame(1, $second->whatsappMessages()->count());
    }

    public function test_deleting_an_order_keeps_the_message_and_nulls_the_link(): void
    {
        $order = $this->createOrderWithCustomer([], [], quantity: 0);
        $conversation = WhatsAppConversation::create(['provider_chat_id' => '20100000000@c.us']);

        $message = $this->outboundMessage($conversation, $order->id);

        $order->delete();

        $this->assertNull($message->refresh()->order_id);
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $message->id]);
    }

    private function outboundMessage(WhatsAppConversation $conversation, ?int $orderId): WhatsAppMessage
    {
        return $conversation->messages()->create([
            'order_id' => $orderId,
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => WhatsAppMessageType::Text,
            'body' => 'Hello',
            'occurred_at' => now(),
        ]);
    }
}
