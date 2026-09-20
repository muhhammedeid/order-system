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

    /** @param array<int, int>|int $variantIds */
    public static function add(array|int $variantIds, int $quantity): void
    {
        $variantIds = is_array($variantIds) ? $variantIds : [$variantIds];
        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));
        sort($variantIds);
        $lineId = sha1(implode(',', $variantIds));
        $items = self::items();

        foreach ($items as $index => $item) {
            if (($item['line_id'] ?? null) === $lineId) {
                $items[$index]['quantity'] += $quantity;
                session()->put(self::SESSION_KEY, $items);

                return;
            }
        }

        $items[] = [
            'line_id' => $lineId,
            'variant_id' => $variantIds[0],
            'variant_ids' => $variantIds,
            'quantity' => $quantity,
        ];

        session()->put(self::SESSION_KEY, $items);
    }

    public static function update(string|int $identifier, int $quantity): void
    {
        $items = self::items();

        foreach ($items as $index => $item) {
            if (self::matches($item, $identifier)) {
                $items[$index]['quantity'] = $quantity;
                session()->put(self::SESSION_KEY, $items);

                return;
            }
        }
    }

    public static function remove(string|int $identifier): void
    {
        $items = array_values(array_filter(
            self::items(),
            fn (array $item): bool => ! self::matches($item, $identifier),
        ));

        session()->put(self::SESSION_KEY, $items);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function count(): int
    {
        return self::hydrated()['total_quantity'];
    }

    /** @return array<int, int> */
    public static function variantIdsFor(string|int $identifier): array
    {
        foreach (self::items() as $item) {
            if (self::matches($item, $identifier)) {
                return array_values(array_unique(array_map(
                    'intval',
                    $item['variant_ids'] ?? [$item['variant_id']],
                )));
            }
        }

        return [];
    }

    /** @param array<int, int> $variantIds */
    public static function requestedQuantityFor(array $variantIds): int
    {
        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));
        sort($variantIds);
        $lineId = sha1(implode(',', $variantIds));

        foreach (self::items() as $item) {
            if (($item['line_id'] ?? null) === $lineId) {
                return (int) ($item['quantity'] ?? 0);
            }
        }

        return 0;
    }

    public static function hydrated(): array
    {
        $variantIds = collect(self::items())
            ->flatMap(fn (array $item): array => $item['variant_ids'] ?? [$item['variant_id']])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with(['product:id,slug,name,product_code,price_visibility,price,active,size_enabled'])
            ->get()
            ->keyBy('id');

        $images = ProductImage::query()
            ->whereIn('product_id', $variants->pluck('product.id')->filter()->unique()->values())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['product_id', 'image_path'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->first()->url());

        $items = [];
        $totalQuantity = 0;
        $publicTotal = '0.00';

        foreach (self::items() as $storedItem) {
            $ids = array_values(array_unique(array_map(
                'intval',
                $storedItem['variant_ids'] ?? [$storedItem['variant_id']],
            )));
            $selected = collect($ids)->map(fn (int $id) => $variants->get($id))->filter()->values();

            if ($selected->count() !== count($ids) || $selected->pluck('product_id')->unique()->count() !== 1) {
                continue;
            }

            $anchor = $selected->first();
            $product = $anchor->product;
            $isPublic = $product->price_visibility === PriceVisibility::PublicPrice;
            $quantity = (int) $storedItem['quantity'];
            $colors = $selected->pluck('color')->filter()->unique()->values()->all();
            $sizes = $selected->pluck('size')->filter()->unique()->values()->all();
            $colorCount = max(1, count($colors));
            $piecesQuantity = $quantity * $colorCount;
            $quantityStep = $product->size_enabled
                ? 1
                : self::distributionStep($selected);
            $lineId = $storedItem['line_id'] ?? sha1(implode(',', $ids));

            $items[] = [
                'line_id' => $lineId,
                'variant_id' => $anchor->id,
                'variant_ids' => $ids,
                'quantity' => $quantity,
                'color_count' => $colorCount,
                'pieces_quantity' => $piecesQuantity,
                'quantity_step' => $quantityStep,
                'color' => implode('، ', $colors),
                'size' => implode('، ', $sizes),
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
                'line_total' => $isPublic ? bcmul((string) $product->price, (string) $piecesQuantity, 2) : null,
            ];

            $totalQuantity += $piecesQuantity;

            if ($isPublic) {
                $publicTotal = bcadd($publicTotal, bcmul((string) $product->price, (string) $piecesQuantity, 2), 2);
            }
        }

        return [
            'items' => $items,
            'total_quantity' => $totalQuantity,
            'total_price' => bccomp($publicTotal, '0.00', 2) === 1 ? $publicTotal : null,
        ];
    }

    private static function matches(array $item, string|int $identifier): bool
    {
        return (string) ($item['line_id'] ?? '') === (string) $identifier
            || (int) ($item['variant_id'] ?? 0) === (int) $identifier;
    }

    private static function distributionStep($variants): int
    {
        $sizeCounts = $variants
            ->groupBy(fn (ProductVariant $variant): string => (string) $variant->color)
            ->map(fn ($group): int => $group->pluck('size')->filter()->unique()->count())
            ->filter(fn (int $count): bool => $count > 0)
            ->values();

        if ($sizeCounts->isEmpty()) {
            return 1;
        }

        return $sizeCounts->reduce(
            fn (int $step, int $count): int => self::leastCommonMultiple($step, $count),
            1,
        );
    }

    private static function leastCommonMultiple(int $left, int $right): int
    {
        return intdiv($left * $right, self::greatestCommonDivisor($left, $right));
    }

    private static function greatestCommonDivisor(int $left, int $right): int
    {
        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return max(1, $left);
    }
}
