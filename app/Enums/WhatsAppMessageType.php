<?php

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Location = 'location';
    case Contact = 'contact';
    case Media = 'media';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return __("admin.whatsapp.message_types.{$this->value}");
    }

    public function isText(): bool
    {
        return $this === self::Text;
    }

    public static function fromMimeType(?string $mimeType): self
    {
        $mimeType = strtolower((string) $mimeType);

        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            str_starts_with($mimeType, 'video/') => self::Video,
            str_starts_with($mimeType, 'audio/') => self::Audio,
            str_starts_with($mimeType, 'application/') => self::Document,
            default => self::Media,
        };
    }
}
