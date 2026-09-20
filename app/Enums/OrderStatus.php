<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Confirmed => 'مؤكد',
            self::PartiallyDelivered => 'تم التسليم جزئيًا',
            self::Delivered => 'تم التسليم',
            self::Cancelled => 'ملغي',
        };
    }

    /**
     * Statuses whose orders still reference their product variants
     * operationally; referenced products/variants must not be deleted.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::New->value,
            self::Confirmed->value,
            self::PartiallyDelivered->value,
        ];
    }
}
