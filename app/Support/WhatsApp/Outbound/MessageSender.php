<?php

namespace App\Support\WhatsApp\Outbound;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Order;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The single outbound text lifecycle, shared by the conversation composer and
 * the order communication panel:
 *
 *   pending row -> provider send -> sent + provider id
 *                                -> unknown when acceptance cannot be confirmed
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

        if (! $this->gateway->enabled()) {
            throw WhatsAppException::notConfigured();
        }

        $message = DB::transaction(function () use ($conversation, $body, $orderId): WhatsAppMessage {
            $conversation = WhatsAppConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($orderId !== null && ! Order::query()->whereKey($orderId)->where('customer_id', $conversation->customer_id)->exists()) {
                throw new WhatsAppException('WhatsApp conversation belongs to another customer.');
            }

            return $conversation->messages()->create([
                'order_id' => $orderId,
                'direction' => WhatsAppMessageDirection::Outbound,
                'message_type' => WhatsAppMessageType::Text,
                'body' => $body,
                'status' => WhatsAppMessageStatus::Pending,
                'occurred_at' => now(),
            ]);
        });

        try {
            $sent = $this->gateway->sendText($conversation->provider_chat_id, $body);
            if (blank($sent->providerId)) {
                throw WhatsAppException::unexpectedResponse('send text');
            }
        } catch (Throwable $exception) {
            $message->forceFill(['status' => WhatsAppMessageStatus::Unknown])->save();

            throw $exception instanceof WhatsAppException ? $exception : WhatsAppException::unreachable('send text');
        }

        $message->forceFill([
            'provider_message_id' => $sent->providerId,
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
