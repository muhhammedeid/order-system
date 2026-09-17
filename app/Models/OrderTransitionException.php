<?php

namespace App\Models;

use App\Enums\OrderStatus;
use RuntimeException;

class OrderTransitionException extends RuntimeException
{
    public static function forStatus(?OrderStatus $status): self
    {
        $label = $status?->label() ?? 'غير معروف';

        return new self("لا يمكن تغيير حالة الطلب من {$label} بهذه الطريقة");
    }
}
