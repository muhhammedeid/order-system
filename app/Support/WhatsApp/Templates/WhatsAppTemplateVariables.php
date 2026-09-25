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

    public const ORDER_NUMBER = 'order_number';

    public const ORDER_STATUS = 'order_status';

    public const ORDER_ITEMS = 'order_items';

    public const TOTAL_QUANTITY = 'total_quantity';

    public const DELIVERED_QUANTITY = 'delivered_quantity';

    public const REMAINING_QUANTITY = 'remaining_quantity';

    /**
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return array_merge(
            self::customerContext(),
            self::orderContext(),
        );
    }

    /**
     * Variables available to manually composed customer/product templates.
     *
     * @return array<int, string>
     */
    public static function customerContext(): array
    {
        return [
            self::CUSTOMER_NAME,
            self::PRODUCT_NAME,
            self::PRODUCT_CODE,
            self::BUSINESS_NAME,
        ];
    }

    /**
     * Variables available to automatic order-status templates.
     *
     * @return array<int, string>
     */
    public static function orderContext(): array
    {
        return [
            self::CUSTOMER_NAME,
            self::BUSINESS_NAME,
            self::ORDER_NUMBER,
            self::ORDER_STATUS,
            self::ORDER_ITEMS,
            self::TOTAL_QUANTITY,
            self::DELIVERED_QUANTITY,
            self::REMAINING_QUANTITY,
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
     * @return array<int, string>
     */
    public static function orderOnlyContext(): array
    {
        return [
            self::ORDER_NUMBER,
            self::ORDER_STATUS,
            self::ORDER_ITEMS,
            self::TOTAL_QUANTITY,
            self::DELIVERED_QUANTITY,
            self::REMAINING_QUANTITY,
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
     * @param  array<int, string>|null  $allowed
     * @return array<int, string>
     */
    public static function unknownTokens(string $body, ?array $allowed = null): array
    {
        $allowed ??= self::allowed();

        return array_values(array_filter(
            self::tokens($body),
            fn (string $token): bool => ! in_array($token, $allowed, true),
        ));
    }

    public static function usesProductContext(string $body): bool
    {
        return array_intersect(self::tokens($body), self::productContext()) !== [];
    }

    public static function usesOrderContext(string $body): bool
    {
        return array_intersect(self::tokens($body), self::orderOnlyContext()) !== [];
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
