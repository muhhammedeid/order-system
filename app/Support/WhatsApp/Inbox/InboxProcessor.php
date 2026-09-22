<?php

namespace App\Support\WhatsApp\Inbox;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppConversation;
use App\Support\WhatsApp\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists Inbox history from verified webhook events.
 *
 * - `message` (fromMe=false) is inbound.
 * - `message.any` (fromMe=true, source=app) is a phone-sent outbound message.
 * - `message.any` (source=api) is ignored: the Laravel outbound flow already
 *   persisted that message.
 * - `message.ack` updates the matching outbound message monotonically.
 *
 * Processing is idempotent: the webhook envelope dedupe runs before this
 * class and the (conversation_id, provider_message_id) unique index is the
 * database-level defense. No message body is ever logged.
 */
class InboxProcessor
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    public function handle(WebhookEvent $event): void
    {
        match ($event->event) {
            'message' => $this->ingestInbound($event->payload),
            'message.any' => $this->ingestAny($event->payload),
            'message.ack' => $this->applyAck($event->payload),
            default => null,
        };
    }

    private function ingestInbound(array $payload): void
    {
        $message = InboundMessage::fromPayload($payload);

        if ($message === null || $message->fromMe || ! $message->isOneToOne()) {
            return;
        }

        $this->store($message, unread: true);
    }

    private function ingestAny(array $payload): void
    {
        $message = InboundMessage::fromPayload($payload);

        // Inbound traffic is handled by the `message` event; `source=api`
        // events are our own outbound sends, already persisted by the panel.
        if ($message === null
            || ! $message->fromMe
            || $message->source !== 'app'
            || ! $message->isOneToOne()) {
            return;
        }

        $this->store($message, unread: false, status: WhatsAppMessageStatus::Sent);
    }

    private function store(InboundMessage $message, bool $unread, ?WhatsAppMessageStatus $status = null): void
    {
        DB::transaction(function () use ($message, $unread, $status): void {
            $conversation = WhatsAppConversation::query()->firstOrCreate([
                'provider_chat_id' => $message->chatId,
            ]);

            $this->resolveIdentity($conversation, $message->chatId);

            $occurredAt = $message->timestamp !== null
                ? Carbon::createFromTimestamp($message->timestamp)
                : now();

            if (! $this->storeMessage($conversation, $message, $status, $occurredAt)) {
                return;
            }

            $conversation->forceFill([
                'last_message_at' => $occurredAt,
                'last_message_preview' => $message->type->isText()
                    ? Str::limit(trim((string) $message->body), 160, '')
                    : null,
                'last_message_direction' => ($message->fromMe
                    ? WhatsAppMessageDirection::Outbound
                    : WhatsAppMessageDirection::Inbound)->value,
                'unread_count' => $unread
                    ? ((int) $conversation->unread_count) + 1
                    : (int) $conversation->unread_count,
            ])->save();
        });
    }

    /**
     * Best-effort verified phone resolution and customer linking. Failures
     * never block conversation creation or message storage.
     */
    private function resolveIdentity(WhatsAppConversation $conversation, string $chatId): void
    {
        if ($conversation->resolved_phone === null) {
            $phone = $this->gateway->resolvePhoneNumber($chatId);

            if (filled($phone)) {
                $conversation->forceFill(['resolved_phone' => $phone])->save();
            }
        }

        if ($conversation->customer_id !== null || $conversation->resolved_phone === null) {
            return;
        }

        $customer = CustomerMatcher::linkableCustomer($conversation->resolved_phone);

        if ($customer !== null) {
            $conversation->forceFill(['customer_id' => $customer->id])->save();
        }
    }

    private function storeMessage(
        WhatsAppConversation $conversation,
        InboundMessage $message,
        ?WhatsAppMessageStatus $status,
        Carbon $occurredAt,
    ): bool {
        $attributes = [
            'direction' => $message->fromMe
                ? WhatsAppMessageDirection::Outbound
                : WhatsAppMessageDirection::Inbound,
            'message_type' => $message->type,
            'body' => $message->type->isText() ? $message->body : null,
            'status' => $status,
            'occurred_at' => $occurredAt,
        ];

        if ($message->providerMessageId === null) {
            $conversation->messages()->create($attributes);

            return true;
        }

        $existing = $conversation->messages()
            ->where('provider_message_id', $message->providerMessageId)
            ->exists();

        if ($existing) {
            return false;
        }

        try {
            $conversation->messages()->create($attributes + [
                'provider_message_id' => $message->providerMessageId,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    private function applyAck(array $payload): void
    {
        $parsed = ProviderMessageId::parse($payload['id'] ?? null);

        if ($parsed === null || ! $parsed['fromMe']) {
            return;
        }

        $ack = is_numeric($payload['ack'] ?? null) ? (int) $payload['ack'] : null;
        $ackName = is_string($payload['ackName'] ?? null) ? $payload['ackName'] : null;

        DB::transaction(function () use ($parsed, $ack, $ackName): void {
            $conversation = WhatsAppConversation::query()
                ->where('provider_chat_id', $parsed['chatId'])
                ->first();

            if ($conversation === null) {
                return;
            }

            $message = $conversation->messages()
                ->where('provider_message_id', $parsed['messageId'])
                ->first();

            if ($message === null || ! $message->isOutbound()) {
                return;
            }

            $message->applyProviderAck($ack, $ackName);
        });
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }
}
