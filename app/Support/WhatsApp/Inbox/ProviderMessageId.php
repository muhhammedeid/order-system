<?php

namespace App\Support\WhatsApp\Inbox;

/**
 * WAHA message identifiers are `{fromMe}_{chatId}_{messageId}` for events and
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
        $messageId = end($parts);

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
}
