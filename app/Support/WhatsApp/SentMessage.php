<?php

namespace App\Support\WhatsApp;

/**
 * Provider-neutral outbound receipt. `providerId` is the
 * opaque adapter-normalized correlation identifier.
 */
class SentMessage
{
    public function __construct(
        public readonly string $providerId,
        public readonly ?int $timestamp = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'timestamp' => $this->timestamp,
        ];
    }
}
