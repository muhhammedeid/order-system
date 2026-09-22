<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppMessageAckTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_ack_upgrades_sent_to_delivered(): void
    {
        $message = $this->outboundMessage(WhatsAppMessageStatus::Sent);

        $changed = $message->applyProviderAck(2, 'DEVICE');

        $this->assertTrue($changed);
        $this->assertSame(WhatsAppMessageStatus::Delivered, $message->refresh()->status);
        $this->assertSame(2, $message->provider_ack);
        $this->assertSame('DEVICE', $message->provider_ack_name);
    }

    public function test_ack_never_downgrades_status_but_keeps_raw_values(): void
    {
        $message = $this->outboundMessage(WhatsAppMessageStatus::Delivered);

        $changed = $message->applyProviderAck(1, 'SERVER');

        $this->assertFalse($changed);
        $this->assertSame(WhatsAppMessageStatus::Delivered, $message->refresh()->status);
        $this->assertSame(1, $message->provider_ack);
        $this->assertSame('SERVER', $message->provider_ack_name);
    }

    public function test_unknown_ack_name_preserves_status_but_stores_raw_values(): void
    {
        $message = $this->outboundMessage(WhatsAppMessageStatus::Sent);

        $changed = $message->applyProviderAck(99, 'SOMETHING_NEW');

        $this->assertFalse($changed);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->refresh()->status);
        $this->assertSame(99, $message->provider_ack);
        $this->assertSame('SOMETHING_NEW', $message->provider_ack_name);
    }

    public function test_error_ack_marks_a_sent_message_as_failed(): void
    {
        $message = $this->outboundMessage(WhatsAppMessageStatus::Sent);

        $changed = $message->applyProviderAck(-1, 'ERROR');

        $this->assertTrue($changed);
        $this->assertSame(WhatsAppMessageStatus::Failed, $message->refresh()->status);
    }

    public function test_failed_is_terminal(): void
    {
        $message = $this->outboundMessage(WhatsAppMessageStatus::Failed);

        $changed = $message->applyProviderAck(1, 'SERVER');

        $this->assertFalse($changed);
        $this->assertSame(WhatsAppMessageStatus::Failed, $message->refresh()->status);
    }

    private function outboundMessage(WhatsAppMessageStatus $status): WhatsAppMessage
    {
        $conversation = WhatsAppConversation::create([
            'provider_chat_id' => '20112347663@c.us',
        ]);

        return $conversation->messages()->create([
            'provider_message_id' => '3EB0ABC',
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => WhatsAppMessageType::Text,
            'body' => 'Hello',
            'status' => $status,
            'occurred_at' => now(),
        ]);
    }
}
