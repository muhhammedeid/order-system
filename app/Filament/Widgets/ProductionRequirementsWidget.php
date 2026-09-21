<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use App\Models\Product;
use Filament\Widgets\Widget;

/**
 * Current production requirements catalog: one card per product with a
 * positive outstanding quantity on confirmed / partially delivered
 * orders. Every card links to the filtered source rows on the
 * ProductionRequirements page, so quantities reconcile exactly.
 */
class ProductionRequirementsWidget extends Widget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.production-requirements';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $totals = OrderItem::productProductionTotals();

        $products = Product::query()
            ->whereIn('id', $totals->pluck('product_id'))
            ->with([
                'images' => fn ($query) => $query->select('id', 'product_id', 'image_path', 'sort_order'),
                'variants' => fn ($query) => $query->select('id', 'product_id', 'color')->orderBy('id'),
            ])
            ->get()
            ->keyBy('id');

        $requirements = $totals
            ->map(function (object $row) use ($products): ?array {
                $product = $products->get($row->product_id);

                if (! $product) {
                    return null;
                }

                $isPerColor = ! $product->color_enabled;
                $displayQuantity = $isPerColor
                    ? (int) $row->requested_quantity
                    : (int) $row->required_quantity;

                return [
                    'id' => $product->getKey(),
                    'name' => $product->name,
                    'code' => $product->product_code,
                    'active' => (bool) $product->active,
                    'image' => $product->images->first()?->url(),
                    'required_quantity' => $displayQuantity,
                    'quantity_mode' => $isPerColor ? 'per_color' : 'total',
                    'color_breakdown' => $isPerColor
                        ? $product->variants
                            ->pluck('color')
                            ->filter()
                            ->unique()
                            ->values()
                            ->map(fn (string $color): array => [
                                'color' => $color,
                                'quantity' => $displayQuantity,
                            ])
                            ->all()
                        : [],
                    'orders_count' => (int) $row->orders_count,
                ];
            })
            ->filter()
            ->values();

        return [
            'requirements' => $requirements,
        ];
    }
}
