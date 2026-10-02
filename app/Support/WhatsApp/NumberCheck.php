<?php

namespace App\Support\WhatsApp;

/**
 * Typed result of a provider number check.
 *
 * A missing number and a provider failure are deliberately different:
 * `notExists()` means the provider successfully confirmed the number is not a
 * WhatsApp account, while transport/configuration/unexpected-response failures
 * throw WhatsAppException instead of being reported as an invalid number.
 */
class NumberCheck
{
    private function __construct(
        public readonly bool $exists,
        public readonly ?string $chatId,
        public readonly ?string $phone,
    ) {}

    public static function exists(string $chatId, ?string $phone = null): self
    {
        return new self(true, $chatId, $phone);
    }

    public static function notExists(): self
    {
        return new self(false, null, null);
    }
}
