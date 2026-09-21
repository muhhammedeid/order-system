<?php

namespace App\Filament\Widgets;

use App\Models\OrderItemColorQuantity;
use App\Models\Product;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Current production requirements catalog: one card per product with at
 * least one ordered color that still requires production on confirmed /
 * partially delivered orders. Every card uses the same structure and shows
 * the outstanding quantity of each pending color, taken from the immutable
 * order snapshot; colors that were fully delivered never appear.
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
        $cards = OrderItemColorQuantity::productionCards($productId);

        $products = Product::query()
            ->whereIn('id', $cards->pluck('product_id'))
            ->with([
                'images' => fn ($query) => $query->select('id', 'product_id', 'image_path', 'sort_order'),
            ])
            ->get()
            ->keyBy('id');

        return $cards
            ->map(function (object $card) use ($products): ?array {
                $product = $products->get($card->product_id);

                if (! $product) {
                    return null;
                }

                return [
                    'id' => $product->getKey(),
                    'name' => $product->name,
                    'code' => $product->product_code,
                    'active' => (bool) $product->active,
                    'image' => $product->images->first()?->url(),
                    // Only colors with a positive remaining quantity; each
                    // one carries its own exact outstanding amount.
                    'pending_colors' => $card->pending_colors,
                    'uniform_remaining' => $card->uniform_remaining,
                    'orders_count' => (int) $card->orders_count,
                ];
            })
            ->filter()
            ->values();
    }
}
