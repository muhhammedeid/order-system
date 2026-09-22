<?php

namespace App\Support\WhatsApp;

/**
 * Verified phone handling. A phone number is only ever taken from a regular
 * `@c.us` chat id; LID identifiers are never converted into phone numbers.
 */
class PhoneNumber
{
    public static function fromChatId(?string $chatId): ?string
    {
        if (! is_string($chatId)) {
            return null;
        }

        return preg_match('/^([0-9]{6,15})@c\.us$/', $chatId, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * Deterministic spelling variants used for exact customer matching.
     * No fuzzy matching: every candidate is a full value.
     *
     * @return array<int, string>
     */
    public static function candidates(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return [];
        }

        $candidates = [$digits];

        if (str_starts_with($digits, '00')) {
            $candidates[] = substr($digits, 2);
        }

        foreach (array_unique($candidates) as $candidate) {
            $candidates[] = '+'.$candidate;
        }

        // Egypt: international 20XXXXXXXXXX <-> national 0XXXXXXXXXX.
        foreach (array_unique($candidates) as $candidate) {
            $plain = ltrim($candidate, '+');

            if (str_starts_with($plain, '20') && strlen($plain) >= 11) {
                $candidates[] = '0'.substr($plain, 2);
            } elseif (str_starts_with($plain, '0') && strlen($plain) >= 10) {
                $candidates[] = substr($plain, 1);
                $candidates[] = '20'.substr($plain, 1);
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}
