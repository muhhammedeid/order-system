<?php

namespace App\Support\WhatsApp\Inbox;

/**
 * WAHA event identifiers are `{fromMe}_{chatId}_{messageId}[_{participant}]` and
 * a bare `{messageId}` token in `sendText`/`sendMedia` responses. The chat
 * context is part of the provider identity, so callers must always combine
 * the returned message token with the conversation.
 */
class ProviderMessageId
{
    /**
     * @return array{fromMe: bool, chatId: string, messageId: string}|null
     */
    public static function parse(?string $id): ?array
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        $parts = explode('_', $id);

        if (count($parts) < 3) {
            return null;
        }

        $prefix = strtolower($parts[0]);

        if (! in_array($prefix, ['true', 'false'], true)) {
            return null;
        }

        $chatId = $parts[1];
        $messageId = $parts[2];

        if ($chatId === '' || ! is_string($messageId) || $messageId === '') {
            return null;
        }

        return [
            'fromMe' => $prefix === 'true',
            'chatId' => $chatId,
            'messageId' => $messageId,
        ];
    }

    /**
     * The message token used for storage and correlation. Full event ids are
     * reduced to their message-id segment; bare send-response ids pass
     * through unchanged.
     */
    public static function normalize(?string $id): ?string
    {
        $parsed = self::parse($id);

        if ($parsed !== null) {
            return $parsed['messageId'];
        }

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Privacy-safe diagnostic form of a provider id. Event ids embed the chat
     * identity (phone or LID) in the middle segment, so application logs use
     * the bare message token only when it is provably identity-free, and a
     * truncated one-way hash otherwise. Never used for storage or correlation.
     */
    public static function safeForLogging(?string $id): ?string
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        $token = self::normalize($id);

        if ($token !== null && self::isIdentityFreeToken($token)) {
            return $token;
        }

        return 'sha256:'.substr(hash('sha256', $id), 0, 16);
    }

    private static function isIdentityFreeToken(string $token): bool
    {
        if (strlen($token) > 64) {
            return false;
        }

        if (str_contains($token, '@') || str_contains($token, '+')) {
            return false;
        }

        if (preg_match('/^[0-9]+$/', $token) === 1) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._:-]+$/', $token) === 1;
    }
}
