<?php

namespace App\Support\WhatsApp\Inbox;

use App\Models\Customer;
use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deterministic customer matching from a verified phone number. No fuzzy
 * matching and no customer creation: ambiguous or unknown numbers leave the
 * conversation unlinked.
 */
class CustomerMatcher
{
    public static function linkableCustomer(?string $phone): ?Customer
    {
        if (! filled($phone)) {
            return null;
        }

        $candidates = PhoneNumber::candidates((string) $phone);
        $normalized = PhoneNumber::normalize($phone);

        if ($candidates === []) {
            return null;
        }

        $matches = Customer::query()
            ->where(function (Builder $query) use ($candidates, $normalized): void {
                $query->whereIn('phone', $candidates)
                    ->orWhereIn('whatsapp', $candidates)
                    ->orWhere('phone_normalized', $normalized)
                    ->orWhere('whatsapp_normalized', $normalized);
            })
            ->limit(2)
            ->get(['id']);

        return $matches->count() === 1
            ? Customer::query()->find($matches->first()->id)
            : null;
    }
}
