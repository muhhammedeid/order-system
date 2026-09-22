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

    public static function fromArray(array $data): self
    {
        return new self(
            providerId: (string) ($data['id'] ?? $data['_data']['id']['id'] ?? ''),
            timestamp: isset($data['timestamp']) ? (int) $data['timestamp'] : null,
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
