<?php

namespace App\Support;

use App\Enums\PriceVisibility;
use App\Models\ProductImage;
use App\Models\ProductVariant;

class Cart
{
    public const SESSION_KEY = 'cart';

    public static function items(): array
    {
        $items = session(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    public static function add(int $variantId, int $quantity): void
    {
        $items = self::items();

        foreach ($items as $index => $item) {
            if ($item['variant_id'] === $variantId) {
                $items[$index]['quantity'] += $quantity;
                session()->put(self::SESSION_KEY, $items);

                return;
            }
        }

        $items[] = ['variant_id' => $variantId, 'quantity' => $quantity];

        session()->put(self::SESSION_KEY, $items);
    }

    public static function update(int $variantId, int $quantity): void
    {
        $items = self::items();

        foreach ($items as $index => $item) {
            if ($item['variant_id'] === $variantId) {
                $items[$index]['quantity'] = $quantity;
                session()->put(self::SESSION_KEY, $items);

                return;
            }
        }
    }

    public static function remove(int $variantId): void
    {
        $items = array_values(array_filter(
            self::items(),
            fn ($item) => $item['variant_id'] !== $variantId,
        ));

        session()->put(self::SESSION_KEY, $items);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function count(): int
    {
        return array_sum(array_column(self::items(), 'quantity'));
    }

    /**
     * Hydrated cart lines with variant/product data for display.
     * Hidden prices are never included for request_price products.
     */
    public static function hydrated(): array
    {
        $variants = ProductVariant::query()
            ->whereIn('id', array_column(self::items(), 'variant_id'))
            ->with(['product:id,slug,name,product_code,price_visibility,price,active'])
            ->get()
            ->keyBy('id');

        $images = ProductImage::query()
            ->whereIn('product_id', $variants->pluck('product.id')->filter()->unique()->values())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['product_id', 'image_path'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->first()->image_path);

        $items = [];
        $totalQuantity = 0;
        $publicTotal = 0;

        foreach (self::items() as $item) {
            $variant = $variants->get($item['variant_id']);

            if (! $variant) {
                continue;
            }

            $product = $variant->product;
            $isPublic = $product->price_visibility === PriceVisibility::PublicPrice;
            $quantity = (int) $item['quantity'];

            $items[] = [
                'variant_id' => $variant->id,
                'quantity' => $quantity,
                'color' => $variant->color,
                'size' => $variant->size,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'product_code' => $product->product_code,
                    'slug' => $product->slug,
                    'price_visibility' => $product->price_visibility->value,
                    'price' => $isPublic ? $product->price : null,
                    'image' => $images->get($product->id),
                ],
                'unit_price' => $isPublic ? $product->price : null,
                'line_total' => $isPublic ? bcmul((string) $product->price, (string) $quantity, 2) : null,
            ];

            $totalQuantity += $quantity;

            if ($isPublic) {
                $publicTotal = bcadd((string) $publicTotal, bcmul((string) $product->price, (string) $quantity, 2), 2);
            }
        }

        return [
            'items' => $items,
            'total_quantity' => $totalQuantity,
            'total_price' => $publicTotal > 0 ? $publicTotal : null,
        ];
    }
}
