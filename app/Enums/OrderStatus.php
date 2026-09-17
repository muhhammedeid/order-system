<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Exported = 'exported';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Confirmed => 'مؤكد',
            self::Exported => 'مُصدَّر',
            self::Cancelled => 'ملغي',
        };
    }
}
