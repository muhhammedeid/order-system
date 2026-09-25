<?php

namespace App\Support\WhatsApp\Order;

/**
 * Outcome of an automatic order notification attempt. Keys are the template
 * keys that were sent, failed or skipped; reasons are fixed, non-PII codes.
 */
final class OrderNotificationResult
{
    /**
     * @param  array<int, string>  $sent
     * @param  array<int, string>  $failed
     * @param  array<int, string>  $skipped
     */
    public function __construct(
        public readonly array $sent = [],
        public readonly array $failed = [],
        public readonly array $skipped = [],
    ) {}

    public function merge(self $other): self
    {
        return new self(
            array_merge($this->sent, $other->sent),
            array_merge($this->failed, $other->failed),
            array_merge($this->skipped, $other->skipped),
        );
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
