<?php

namespace App\Support\WhatsApp\Outbound;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp\Inbox\ProviderMessageId;
use App\Support\WhatsApp\WhatsAppException;

/**
 * The single outbound text lifecycle, shared by the conversation composer and
 * the order communication panel:
 *
 *   pending row -> provider send -> sent + provider id
 *                                -> failed retained on provider failure
 *
 * Callers own their notifications; this class only owns persistence and the
 * provider call. No queue, no repository, no events.
 */
class MessageSender
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    /**
     * @throws WhatsAppException when the provider cannot receive the message
     */
    public function send(WhatsAppConversation $conversation, string $body, ?int $orderId = null): WhatsAppMessage
    {
        $body = trim($body);

        $message = $conversation->messages()->create([
            'order_id' => $orderId,
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => WhatsAppMessageType::Text,
            'body' => $body,
            'status' => WhatsAppMessageStatus::Pending,
            'occurred_at' => now(),
        ]);

        try {
            $sent = $this->gateway->sendText($conversation->provider_chat_id, $body);
        } catch (WhatsAppException $exception) {
            $message->forceFill(['status' => WhatsAppMessageStatus::Failed])->save();

            throw $exception;
        }

        $message->forceFill([
            'provider_message_id' => ProviderMessageId::normalize($sent->providerId),
            'status' => WhatsAppMessageStatus::Sent,
        ])->save();

        $conversation->forceFill([
            'last_message_at' => $message->occurred_at,
            'last_message_preview' => $message->previewText(),
            'last_message_direction' => WhatsAppMessageDirection::Outbound->value,
        ])->save();

        return $message;
    }
}
