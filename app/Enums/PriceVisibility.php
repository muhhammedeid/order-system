<?php

namespace App\Enums;

enum PriceVisibility: string
{
    case PublicPrice = 'public';
    case RequestPrice = 'request_price';

    public function label(): string
    {
        return __("domain.price_visibility.{$this->value}");
    }
}
