<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PriceVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * Technical upper bound shared by every quantity entry point
     * (unsigned INT column limit).
     */
    public const MAX_QUANTITY = 4294967295;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_code',
        'product_name',
        'color',
        'size',
        'requested_quantity',
        'color_count',
        'quantity',
        'unit_price',
        'price_visibility',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'color_count' => 'integer',
            'quantity' => 'integer',
            'delivered_quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $requestedQuantity = (int) ($item->requested_quantity ?: $item->quantity);
            $colorCount = max(1, (int) ($item->color_count ?: 1));

            if ($requestedQuantity < 1 || $requestedQuantity > intdiv(self::MAX_QUANTITY, $colorCount)) {
                throw ValidationException::withMessages([
                    'quantity' => 'إجمالي عدد القطع يتجاوز الحد المسموح.',
                ]);
            }

            $item->requested_quantity = $requestedQuantity;
            $item->color_count = $colorCount;
            $item->quantity = self::physicalQuantity($requestedQuantity, $colorCount);

            $delivered = (int) ($item->delivered_quantity ?? 0);

            if ($delivered < 0 || $delivered > (int) $item->quantity) {
                throw new \InvalidArgumentException('الكمية المُسلَّمة يجب أن تكون بين صفر والكمية المطلوبة');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Derived, never stored: quantity - delivered_quantity.
     */
    protected function remainingQuantity(): Attribute
    {
        return Attribute::get(fn () => (int) $this->quantity - (int) $this->delivered_quantity);
    }

    /**
     * Number of colors this line covers. Always at least one, so the
     * per-color unit stays well defined for historical rows.
     */
    public function effectiveColorCount(): int
    {
        return max(1, (int) $this->color_count);
    }

    /**
     * Individual color names included in this line. The snapshot stores a
     * human-readable list (Arabic or Latin commas), never rewritten later.
     *
     * @return array<int, string>
     */
    public function colors(): array
    {
        $colors = preg_split('/[،,]+/u', (string) $this->color, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $colors))));
    }

    /**
     * Delivered pieces expressed in the production-requirements unit
     * (quantity per color). Deliveries are stored as the physical piece
     * total, so the conversion lives here only.
     */
    protected function deliveredQuantityPerColor(): Attribute
    {
        return Attribute::get(
            fn (): int => self::perColorQuantityFromPhysical((int) $this->delivered_quantity, $this->effectiveColorCount()),
        );
    }

    /**
     * Remaining pieces per color: requested quantity minus the delivered
     * per-color quantity. Never fractional.
     */
    protected function remainingQuantityPerColor(): Attribute
    {
        return Attribute::get(
            fn (): int => max(0, (int) $this->requested_quantity - $this->delivered_quantity_per_color),
        );
    }

    /**
     * Whole per-color quantities still available for partial delivery: only
     * whole sets can be expressed in the per-color input without exceeding
     * the stored physical remainder. Historical rows whose delivered
     * quantity is not a multiple of the color count keep their odd
     * remainder for the deliver-all action.
     */
    protected function deliverableQuantityPerColor(): Attribute
    {
        return Attribute::get(
            fn (): int => self::perColorQuantityFromPhysical(max(0, (int) $this->remaining_quantity), $this->effectiveColorCount()),
        );
    }

    /**
     * Converts a per-color quantity into the stored physical piece total.
     * The single conversion point for delivery input, order editing, and
     * the derived quantity invariant.
     */
    public static function physicalQuantity(int $quantityPerColor, int $colorCount): int
    {
        return $quantityPerColor * max(1, $colorCount);
    }

    /**
     * Converts a stored physical piece total back into the per-color unit,
     * always flooring so legacy rows never report more delivered pieces
     * than were actually recorded.
     */
    public static function perColorQuantityFromPhysical(int $physicalQuantity, int $colorCount): int
    {
        return intdiv(max(0, $physicalQuantity), max(1, $colorCount));
    }

    /**
     * Converts a per-color quantity into the stored physical piece total.
     * The single conversion point for delivery input.
     */
    public function physicalQuantityForPerColor(int $quantityPerColor): int
    {
        return self::physicalQuantity($quantityPerColor, $this->effectiveColorCount());
    }

    /**
     * Trusted snapshot attributes for an order item, built from the selected
     * variant and its product. Applies the existing price-visibility policy:
     * request_price products never expose an internal unit price.
     *
     * @return array<string, mixed>
     */
    public static function snapshotFromVariant(ProductVariant $variant): array
    {
        $product = $variant->product;

        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_code' => $product->product_code,
            'product_name' => $product->name,
            'color' => $variant->color,
            'size' => $variant->size,
            'unit_price' => $product->price_visibility === PriceVisibility::PublicPrice ? $product->price : null,
            'price_visibility' => $product->price_visibility->value,
        ];
    }

    /**
     * Order statuses whose undelivered item quantities are the current
     * production requirements (R03): confirmed and partially delivered.
     * `new`, `cancelled`, and `delivered` orders never contribute.
     *
     * @return array<int, string>
     */
    public static function productionStatuses(): array
    {
        return [OrderStatus::Confirmed->value, OrderStatus::PartiallyDelivered->value];
    }

    /**
     * Restricts the query to items belonging to orders in production
     * (confirmed or partially delivered).
     */
    public function scopeInProduction(Builder $query): Builder
    {
        return $query->whereHas(
            'order',
            fn (Builder $orderQuery) => $orderQuery->whereIn('status', self::productionStatuses()),
        );
    }

    /**
     * Restricts the query to items with a positive remaining physical
     * quantity (quantity - delivered_quantity). This is equivalent to a
     * positive remaining quantity per color, because the stored total is
     * always the per-color quantity multiplied by the color count.
     */
    public function scopeWithOutstandingQuantity(Builder $query): Builder
    {
        return $query->whereColumn('order_items.delivered_quantity', '<', 'order_items.quantity');
    }

    /**
     * Total remaining production requirement in the approved per-color
     * unit, optionally for one product: the requested quantity of every
     * outstanding line of confirmed / partially delivered orders minus the
     * quantity already delivered per color. Rows in excluded statuses or
     * with zero remaining quantity contribute nothing. The physical piece
     * total (requested quantity × colors, or quantity − delivered_quantity)
     * is never used.
     *
     * The division is aligned to a whole multiple of the color count before
     * dividing, so every engine returns the same floored per-color value
     * without relying on engine-specific rounding.
     */
    public static function productionRemainingQuantityTotal(?int $productId = null): int
    {
        $colorCount = 'CASE WHEN order_items.color_count > 0 THEN order_items.color_count ELSE 1 END';
        $perColorDelivered = "((order_items.delivered_quantity - (order_items.delivered_quantity % {$colorCount})) / {$colorCount})";

        return (int) static::query()
            ->inProduction()
            ->withOutstandingQuantity()
            ->when($productId !== null, fn (Builder $query) => $query->where('order_items.product_id', $productId))
            ->sum(DB::raw("order_items.requested_quantity - {$perColorDelivered}"));
    }

    /**
     * Per-color production requirement totals grouped by product, used by
     * the production-requirements cards. Each outstanding line contributes
     * its requested quantity (the gross quantity required for each of its
     * colors) once to the product total and once to every color captured in
     * its snapshot, so the main quantity is never multiplied by the number
     * of colors and never equals the sum of the color breakdown. Lines with
     * a positive remaining quantity are included regardless of how much of
     * them was already delivered; products with no such line are excluded.
     *
     * @return Collection<int, object{product_id: int, required_quantity: int, orders_count: int, color_quantities: array<string, int>}>
     */
    public static function productProductionTotals(?int $productId = null): Collection
    {
        $items = static::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::productionStatuses())
            ->whereColumn('order_items.delivered_quantity', '<', 'order_items.quantity')
            ->whereNotNull('order_items.product_id')
            ->when($productId !== null, fn (Builder $query) => $query->where('order_items.product_id', $productId))
            ->orderBy('order_items.product_id')
            ->orderBy('order_items.id')
            ->get([
                'order_items.product_id',
                'order_items.order_id',
                'order_items.color',
                'order_items.requested_quantity',
            ]);

        return $items
            ->groupBy('product_id')
            ->map(function (Collection $rows, int|string $groupedProductId): object {
                $colorQuantities = [];
                $orderIds = [];
                $requiredQuantity = 0;

                foreach ($rows as $row) {
                    /** @var self $row */
                    $required = max(0, (int) $row->requested_quantity);
                    $requiredQuantity += $required;
                    $orderIds[(int) $row->order_id] = true;

                    foreach ($row->colors() as $color) {
                        $colorQuantities[$color] = ($colorQuantities[$color] ?? 0) + $required;
                    }
                }

                return (object) [
                    'product_id' => (int) $groupedProductId,
                    'required_quantity' => $requiredQuantity,
                    'orders_count' => count($orderIds),
                    'color_quantities' => $colorQuantities,
                ];
            })
            ->sortByDesc('required_quantity')
            ->values();
    }

    /**
     * Controlled mutation point for delivered quantities (R02 delivery
     * actions). The invariant 0 <= delivered_quantity <= quantity is
     * enforced here, on save and by a database CHECK on MariaDB/MySQL.
     */
    public function setDeliveredQuantity(int $delivered): void
    {
        if ($delivered < 0 || $delivered > (int) $this->quantity) {
            throw new \InvalidArgumentException('الكمية المُسلَّمة يجب أن تكون بين صفر والكمية المطلوبة');
        }

        $this->delivered_quantity = $delivered;
        $this->save();
    }
}
