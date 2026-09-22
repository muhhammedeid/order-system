<?php

namespace App\Support\WhatsApp\Templates;

/**
 * Explicit template-variable allowlist. Token syntax is `{{ name }}` with a
 * lowercase snake-case name; anything else found between braces is rejected.
 */
class WhatsAppTemplateVariables
{
    public const CUSTOMER_NAME = 'customer_name';

    public const PRODUCT_NAME = 'product_name';

    public const PRODUCT_CODE = 'product_code';

    public const BUSINESS_NAME = 'business_name';

    /**
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return [
            self::CUSTOMER_NAME,
            self::PRODUCT_NAME,
            self::PRODUCT_CODE,
            self::BUSINESS_NAME,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function productContext(): array
    {
        return [
            self::PRODUCT_NAME,
            self::PRODUCT_CODE,
        ];
    }

    /**
     * Every `{{ ... }}` token found in the body, in order of appearance.
     *
     * @return array<int, string>
     */
    public static function tokens(string $body): array
    {
        preg_match_all('/\{\{\s*([^{}]*?)\s*\}\}/u', $body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return array<int, string>
     */
    public static function unknownTokens(string $body): array
    {
        return array_values(array_filter(
            self::tokens($body),
            fn (string $token): bool => ! in_array($token, self::allowed(), true),
        ));
    }

    public static function usesProductContext(string $body): bool
    {
        return array_intersect(self::tokens($body), self::productContext()) !== [];
    }

    /**
     * Normalizes `{{ name }}` spacing so substitution keys match exactly.
     * Runs on the template body only, before any value is inserted.
     */
    public static function canonicalize(string $body): string
    {
        return preg_replace_callback(
            '/\{\{\s*([^{}]*?)\s*\}\}/u',
            fn (array $matches): string => '{{'.$matches[1].'}}',
            $body,
        ) ?? $body;
    }
}
