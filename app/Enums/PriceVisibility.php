<?php

namespace App\Enums;

enum PriceVisibility: string
{
    case PublicPrice = 'public';
    case RequestPrice = 'request_price';

    public function label(): string
    {
        return match ($this) {
            self::PublicPrice => 'Public',
            self::RequestPrice => 'Request Price',
        };
    }
}
