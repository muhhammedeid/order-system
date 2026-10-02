<?php

namespace App\Support\WhatsApp\Inbox;

/**
 * Deterministic inbound marketing opt-out: whole-message exact matches from a
 * restricted allowlist after normalization. No substring, fuzzy, NLP or AI
 * matching — «إلغاء الطلب» ("cancel the order") is never an opt-out.
 */
class MarketingOptOut
{
    /**
     * Restricted exact-match allowlist. Bare «إلغاء» / «إيقاف» are ambiguous
     * in an order system and intentionally excluded.
     *
     * @var array<int, string>
     */
    private const KEYWORDS = [
        'stop',
        'unsubscribe',
        'ايقاف الاشتراك',
        'الغاء الاشتراك',
        'لا اريد رسائل',
        'لا اريد عروض',
    ];

    private const DIACRITICS_PATTERN = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E4}\x{06E7}\x{06E8}\x{06EA}-\x{06ED}]/u';

    /**
     * Invisible Unicode format characters (ZWJ/ZWNJ/RLM/LRM/BOM, soft hyphen)
     * must never prevent a whole-message opt-out match. Zero-width space and
     * Unicode separators are treated as plain spaces instead.
     */
    private const FORMAT_PATTERN = '/[\p{Cf}\x{AD}]/u';

    public static function matches(?string $body): bool
    {
        $normalized = self::normalize($body);

        return $normalized !== '' && in_array($normalized, self::KEYWORDS, true);
    }

    /**
     * trim -> remove Arabic diacritics/tatweel -> treat zero-width space and
     * Unicode separators as plain spaces -> remove remaining invisible format
     * characters -> normalize alef variants -> lowercase English -> collapse
     * whitespace -> strip surrounding punctuation/emoji.
     */
    public static function normalize(?string $body): string
    {
        if (! is_string($body)) {
            return '';
        }

        $value = trim($body);
        $value = preg_replace(self::DIACRITICS_PATTERN, '', $value) ?? $value;
        $value = preg_replace('/[\x{200B}\p{Z}]/u', ' ', $value) ?? $value;
        $value = preg_replace(self::FORMAT_PATTERN, '', $value) ?? $value;
        $value = str_replace("\u{0640}", '', $value);
        $value = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $value) ?? $value;

        return trim($value);
    }
}
