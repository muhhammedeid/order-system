<?php

namespace App\Support\WhatsApp;

/**
 * Verified phone handling. A phone number is only ever taken from a regular
 * `@c.us` chat id; LID identifiers are never converted into phone numbers.
 */
class PhoneNumber
{
    /** Egyptian mobiles use country-code digits; legacy values are not guessed. */
    public static function normalize(?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $ascii = strtr(trim($number), array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789'),
        ));
        $digits = preg_replace('/[\s()+.\-]+/u', '', $ascii);
        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^01[0125][0-9]{8}$/', $digits)) {
            return '20'.substr($digits, 1);
        }
        if (preg_match('/^201[0125][0-9]{8}$/', $digits)) {
            return $digits;
        }

        return trim($number);
    }

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
     * Syntactic usability only (no provider verification): a customer-entered
     * number with 6–15 digits, matching the accepted `@c.us` length range.
     */
    public static function isSyntacticallyUsable(?string $number): bool
    {
        if (! is_string($number)
            || preg_match('/\A *\+?[0-9٠-٩۰-۹ ().-]+\z/u', $number) !== 1) {
            return false;
        }

        $digitCount = preg_match_all('/[0-9٠-٩۰-۹]/u', self::normalize($number) ?? '');

        return $digitCount >= 6 && $digitCount <= 15;
    }

    /**
     * Deterministic spelling variants used for exact customer matching.
     * No fuzzy matching: every candidate is a full value.
     *
     * @return array<int, string>
     */
    public static function candidates(string $phone): array
    {
        $phone = self::normalize($phone) ?? '';
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
