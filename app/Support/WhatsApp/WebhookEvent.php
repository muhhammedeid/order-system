<?php

namespace App\Support\WhatsApp;

/**
 * Normalized inbound WAHA webhook event.
 *
 * The WAHA event envelope carries an `id` (lower-case ULID, e.g.
 * "evt_..."), an `event` name, a `session`, a millisecond `timestamp`,
 * and the event-specific `payload`. The HTTP layer also carries
 * X-Webhook-Request-Id and X-Webhook-Timestamp headers.
 *
 * The idempotency key is derived from the envelope id when present and
 * falls back to a hash of the raw body otherwise. The exact key was
 * verified against live WAHA payloads during the P08-W01 spike; see the
 * handoff for the recorded shapes.
 */
class WebhookEvent
{
    public function __construct(
        public readonly string $event,
        public readonly ?string $session,
        public readonly array $payload,
        public readonly ?string $envelopeId = null,
        public readonly ?string $requestId = null,
        public readonly ?int $timestamp = null,
        public readonly string $raw = '',
    ) {}

    public static function fromRequest(array $body, ?string $requestId, ?int $headerTimestamp): self
    {
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];

        return new self(
            event: (string) ($body['event'] ?? 'unknown'),
            session: isset($body['session']) ? (string) $body['session'] : null,
            payload: $payload,
            envelopeId: isset($body['id']) ? (string) $body['id'] : null,
            requestId: $requestId,
            timestamp: isset($body['timestamp']) ? (int) $body['timestamp'] : $headerTimestamp,
            raw: json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        );
    }

    /**
     * Stable key used for duplicate/replay protection.
     *
     * Preference order:
     *   1. WAHA envelope id (event-level unique id, signed with the body).
     *   2. A content hash of the raw body (retries of the same event match).
     *   3. X-Webhook-Request-Id as a last resort.
     */
    public function idempotencyKey(): string
    {
        if (filled($this->envelopeId)) {
            return 'id:'.$this->envelopeId;
        }

        if ($this->raw !== '') {
            return 'body:'.hash('sha256', $this->raw);
        }

        return 'req:'.(string) $this->requestId;
    }

    public function messageId(): ?string
    {
        $id = $this->payload['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function ackName(): ?string
    {
        $ack = $this->payload['ackName'] ?? null;

        return is_string($ack) ? $ack : null;
    }
}
