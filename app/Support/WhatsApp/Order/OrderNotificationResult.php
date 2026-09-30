<?php

namespace App\Support\WhatsApp\Order;

/**
 * Outcome of scheduling an automatic order notification. Keys identify the
 * templates; reasons are fixed, non-PII codes. Delivery is tracked separately.
 */
final class OrderNotificationResult
{
    /**
     * @param  array<int, string>  $sent
     * @param  array<int, string>  $failed
     * @param  array<int, string>  $skipped
     * @param  array<int, string>  $queued
     */
    public function __construct(
        public readonly array $sent = [],
        public readonly array $failed = [],
        public readonly array $skipped = [],
        public readonly array $queued = [],
    ) {}

    public function merge(self $other): self
    {
        return new self(
            array_merge($this->sent, $other->sent),
            array_merge($this->failed, $other->failed),
            array_merge($this->skipped, $other->skipped),
            array_merge($this->queued, $other->queued),
        );
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
