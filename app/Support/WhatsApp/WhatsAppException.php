<?php

namespace App\Support\WhatsApp;

use RuntimeException;

/**
 * Provider-facing failure. The message is always safe to log: it never
 * contains the API key or other credentials.
 */
class WhatsAppException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('WhatsApp integration is not enabled or configured.');
    }

    public static function requestFailed(string $action, int $status): self
    {
        return new self("WhatsApp provider request failed: {$action} (HTTP {$status}).");
    }

    public static function unreachable(string $action): self
    {
        return new self("WhatsApp provider is unreachable or timed out: {$action}.");
    }

    public static function unexpectedResponse(string $action): self
    {
        return new self("WhatsApp provider returned an unexpected response: {$action}.");
    }
}
