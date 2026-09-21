<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use App\Models\Product;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Current production requirements catalog: one card per product with a
 * positive outstanding quantity on confirmed / partially delivered
 * orders. Every card uses the same structure and shows the required
 * quantity per color plus the required color breakdown, whether the
 * colors were forced by the product or selected by the customer.
 *
 * The widget is also embedded at the top of the ProductionRequirements
 * page, where it accepts the page's product filter.
 */
class ProductionRequirementsWidget extends Widget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.production-requirements';

    /**
     * Optional product filter when the widget is embedded in a page.
     */
    public ?int $product = null;

    public function mount(?int $product = null): void
    {
        $this->product = $product;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'requirements' => self::requirementsFor($this->product),
        ];
    }

    /**
     * Card view data for the (optionally filtered) current production
     * requirements. Shared by the dashboard widget and the source page so
     * both always present identical numbers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function requirementsFor(?int $productId = null): Collection
    {
        $totals = OrderItem::productProductionTotals($productId);

        $products = Product::query()
            ->whereIn('id', $totals->pluck('product_id'))
            ->with([
                'images' => fn ($query) => $query->select('id', 'product_id', 'image_path', 'sort_order'),
                'variants' => fn ($query) => $query->select('id', 'product_id', 'color')->orderBy('id'),
            ])
            ->get()
            ->keyBy('id');

        return $totals
            ->map(function (object $row) use ($products): ?array {
                $product = $products->get($row->product_id);

                if (! $product) {
                    return null;
                }

                return [
                    'id' => $product->getKey(),
                    'name' => $product->name,
                    'code' => $product->product_code,
                    'active' => (bool) $product->active,
                    'image' => $product->images->first()?->url(),
                    // Quantity required for each color: the sum of the
                    // requested quantities of the contributing lines.
                    'required_quantity' => (int) $row->required_quantity,
                    'color_breakdown' => self::colorBreakdown($product, (array) $row->color_quantities, (int) $row->required_quantity),
                    'orders_count' => (int) $row->orders_count,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Colors the factory must produce for this product:
     * - color choice disabled: every color automatically included with the
     *   product, each carrying the full per-color requirement;
     * - color choice enabled: only the colors the customer selected, with
     *   the required quantity of every contributing line added.
     *
     * @param  array<string, int>  $colorQuantities
     * @return array<int, array{color: string, quantity: int}>
     */
    private static function colorBreakdown(Product $product, array $colorQuantities, int $requiredQuantity): array
    {
        if (! $product->color_enabled) {
            $forcedColors = $product->variants
                ->pluck('color')
                ->filter()
                ->unique()
                ->values();

            if ($forcedColors->isNotEmpty()) {
                return $forcedColors
                    ->map(fn (string $color): array => [
                        'color' => $color,
                        'quantity' => $requiredQuantity,
                    ])
                    ->all();
            }
        }

        return collect($colorQuantities)
            ->map(fn (int $quantity, int|string $color): array => [
                'color' => (string) $color,
                'quantity' => $quantity,
            ])
            ->values()
            ->all();
    }
}
