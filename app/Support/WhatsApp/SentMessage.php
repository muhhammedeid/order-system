<?php

namespace App\Support\WhatsApp;

/**
 * Minimal normalized result of an outbound WAHA send. `providerId` is the
 * WAHA message id used later to correlate message.ack events.
 */
class SentMessage
{
    public function __construct(
        public readonly string $providerId,
        public readonly ?int $timestamp = null,
    ) {}

    /**
     * WAHA 2026.9.1 (NOWEB) returns the message id under `key.id` and the
     * timestamp as a string `messageTimestamp`. Other shapes keep the
     * documented `id` / `timestamp` fields, which are tried first.
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id']
            ?? $data['key']['id']
            ?? $data['_data']['id']['id']
            ?? '';

        $timestamp = $data['timestamp']
            ?? $data['messageTimestamp']
            ?? null;

        return new self(
            providerId: (string) $id,
            timestamp: is_numeric($timestamp) ? (int) $timestamp : null,
        );
    }

    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'timestamp' => $this->timestamp,
        ];
    }
}
