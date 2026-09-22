<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppConversation;
use App\Support\WhatsApp\Outbound\MessageSender;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class MessageSenderTest extends TestCase
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

    public function test_send_persists_the_order_link_and_marks_sent(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = $this->conversation();

        $message = app(MessageSender::class)->send($conversation, '  Hello  ', $order->id);

        $this->assertSame('Hello', $message->body);
        $this->assertSame($order->id, $message->order_id);
        $this->assertSame(WhatsAppMessageDirection::Outbound, $message->direction);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame('SENT-1', $message->provider_message_id);
        $this->assertContains('send_text', $this->gateway->calls);
    }

    public function test_send_without_an_order_leaves_the_link_empty(): void
    {
        $conversation = $this->conversation();

        $message = app(MessageSender::class)->send($conversation, 'General message');

        $this->assertNull($message->order_id);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
    }

    public function test_provider_failure_keeps_the_failed_message_and_rethrows(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = $this->conversation();
        $this->gateway->throwOnAction = WhatsAppException::requestFailed('send text', 500);

        try {
            app(MessageSender::class)->send($conversation, 'Will fail', $order->id);
            $this->fail('Expected WhatsAppException was not thrown.');
        } catch (WhatsAppException $exception) {
            $this->assertStringContainsString('HTTP 500', $exception->getMessage());
        }

        $message = $conversation->messages()->firstOrFail();

        $this->assertSame(WhatsAppMessageStatus::Failed, $message->status);
        $this->assertNull($message->provider_message_id);
        $this->assertSame($order->id, $message->order_id);
    }

    public function test_conversation_metadata_is_updated_on_success(): void
    {
        $conversation = $this->conversation();

        app(MessageSender::class)->send($conversation, 'Preview text');

        $conversation->refresh();

        $this->assertSame('Preview text', $conversation->last_message_preview);
        $this->assertSame('outbound', $conversation->last_message_direction);
        $this->assertNotNull($conversation->last_message_at);
    }

    private function conversation(): WhatsAppConversation
    {
        return WhatsAppConversation::create(['provider_chat_id' => '20100000000@c.us']);
    }
}
