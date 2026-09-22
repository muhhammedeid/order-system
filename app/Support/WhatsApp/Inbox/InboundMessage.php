<?php

namespace App\Support\WhatsApp\Inbox;

use App\Enums\WhatsAppMessageType;

/**
 * Normalized view of an inbound message/any payload. Only documented payload
 * fields are read; engine `_data` is never touched.
 */
class InboundMessage
{
    public function __construct(
        public readonly bool $fromMe,
        public readonly string $source,
        public readonly string $chatId,
        public readonly ?string $providerMessageId,
        public readonly WhatsAppMessageType $type,
        public readonly ?string $body,
        public readonly ?int $timestamp,
    ) {}

    public static function fromPayload(array $payload): ?self
    {
        $parsed = ProviderMessageId::parse($payload['id'] ?? null);
        $fromMe = (bool) ($payload['fromMe'] ?? ($parsed['fromMe'] ?? false));

        $chatId = $parsed['chatId'] ?? null;

        if ($chatId === null) {
            $candidate = $fromMe ? ($payload['to'] ?? null) : ($payload['from'] ?? null);
            $chatId = is_string($candidate) ? $candidate : null;
        }

        if ($chatId === null || $chatId === '') {
            return null;
        }

        $body = $payload['body'] ?? null;
        $body = is_string($body) && trim($body) !== '' ? $body : null;

        return new self(
            fromMe: $fromMe,
            source: is_string($payload['source'] ?? null) ? $payload['source'] : 'app',
            chatId: $chatId,
            providerMessageId: $parsed['messageId']
                ?? (is_string($payload['id'] ?? null) && $payload['id'] !== '' ? $payload['id'] : null),
            type: self::detectType($payload, $body),
            body: $body,
            timestamp: is_numeric($payload['timestamp'] ?? null) ? (int) $payload['timestamp'] : null,
        );
    }

    /**
     * One-to-one traffic only: groups, broadcasts, status and channels are
     * outside this package's business boundary.
     */
    public function isOneToOne(): bool
    {
        foreach (['@g.us', '@broadcast', '@newsletter'] as $suffix) {
            if (str_ends_with($this->chatId, $suffix)) {
                return false;
            }
        }

        return $this->chatId !== 'status@broadcast';
    }

    private static function detectType(array $payload, ?string $body): WhatsAppMessageType
    {
        if (($payload['hasMedia'] ?? false) === true) {
            $media = $payload['media'] ?? null;
            $mimeType = is_array($media) ? ($media['mimetype'] ?? null) : null;

            return WhatsAppMessageType::fromMimeType(is_string($mimeType) ? $mimeType : null);
        }

        if (filled($payload['location'] ?? null)) {
            return WhatsAppMessageType::Location;
        }

        if (is_array($payload['vCards'] ?? null) && $payload['vCards'] !== []) {
            return WhatsAppMessageType::Contact;
        }

        return $body !== null ? WhatsAppMessageType::Text : WhatsAppMessageType::Unsupported;
    }
}
