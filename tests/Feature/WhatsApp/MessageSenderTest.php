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
        $conversation->update(['customer_id' => $order->customer_id]);

        $message = app(MessageSender::class)->send($conversation, '  Hello  ', $order->id);

        $this->assertSame('Hello', $message->body);
        $this->assertSame($order->id, $message->order_id);
        $this->assertSame(WhatsAppMessageDirection::Outbound, $message->direction);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame('SENT-1', $message->provider_message_id);
        $this->assertContains('send_text', $this->gateway->calls);
    }

    public function test_an_order_cannot_be_sent_to_another_customers_conversation(): void
    {
        $order = $this->createOrderWithCustomer();
        $other = $this->createOrderWithCustomer();
        $conversation = $this->conversation();
        $conversation->update(['customer_id' => $other->customer_id]);
        try {
            app(MessageSender::class)->send($conversation, 'Private order details', $order->id);
            $this->fail('Cross-customer routing must fail closed.');
        } catch (WhatsAppException) {
            $this->assertNotContains('send_text', $this->gateway->calls);
            $this->assertDatabaseCount('whatsapp_messages', 0);
        }
    }

    public function test_send_without_an_order_leaves_the_link_empty(): void
    {
        $conversation = $this->conversation();

        $message = app(MessageSender::class)->send($conversation, 'General message');

        $this->assertNull($message->order_id);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
    }

    public function test_provider_failure_keeps_an_unknown_message_and_rethrows_without_resending(): void
    {
        $order = $this->createOrderWithCustomer();
        $conversation = $this->conversation();
        $conversation->update(['customer_id' => $order->customer_id]);
        $this->gateway->throwOnAction = WhatsAppException::requestFailed('send text', 500);

        try {
            app(MessageSender::class)->send($conversation, 'Will fail', $order->id);
            $this->fail('Expected WhatsAppException was not thrown.');
        } catch (WhatsAppException $exception) {
            $this->assertStringContainsString('HTTP 500', $exception->getMessage());
        }

        $message = $conversation->messages()->firstOrFail();

        $this->assertSame(WhatsAppMessageStatus::Unknown, $message->status);
        $this->assertNull($message->provider_message_id);
        $this->assertSame($order->id, $message->order_id);
        $this->assertSame(1, count(array_filter($this->gateway->calls, fn ($call) => $call === 'send_text')));
    }

    public function test_empty_provider_receipt_is_unknown_and_never_claimed_sent(): void
    {
        $conversation = $this->conversation();
        $this->gateway->sentProviderId = '';
        try {
            app(MessageSender::class)->send($conversation, 'Ambiguous');
            $this->fail('An empty receipt must not be claimed as success.');
        } catch (WhatsAppException) {
            $message = $conversation->messages()->firstOrFail();
            $this->assertSame(WhatsAppMessageStatus::Unknown, $message->status);
            $this->assertNull($message->provider_message_id);
            $this->assertCount(1, $this->gateway->sentTexts);
            $this->assertNull($conversation->fresh()->last_message_at);
        }
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
