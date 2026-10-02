<?php

namespace App\Enums;

enum WhatsAppMessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return __("admin.whatsapp.directions.{$this->value}");
    }

    public function isInbound(): bool
    {
        return $this === self::Inbound;
    }
}
