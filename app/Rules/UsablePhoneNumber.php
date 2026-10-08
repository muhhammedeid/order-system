<?php

namespace App\Rules;

use App\Support\WhatsApp\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UsablePhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumber::isSyntacticallyUsable($value)) {
            $fail('أدخل رقم هاتف صالحًا، مع كود الدولة عند الحاجة.');
        }
    }
}
