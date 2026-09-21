<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative delivered and requested quantity for one ordered color of
 * an order item. The color is an immutable snapshot value taken from the
 * accepted order line; product color changes never affect these rows.
 */
class OrderItemColorQuantity extends Model
{
    use HasFactory;

    /**
     * Delivered quantities are never mass assigned: they only change through
     * the guarded mutation methods and the delivery domain.
     */
    protected $fillable = [
        'color',
        'requested_quantity',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'delivered_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $quantity) {
            $requested = (int) $quantity->requested_quantity;
            $delivered = (int) ($quantity->delivered_quantity ?? 0);

            if ($requested < 1) {
                throw ValidationException::withMessages([
                    'requested_quantity' => 'الكمية المطلوبة لكل لون يجب أن تكون أكبر من صفر',
                ]);
            }

            if ($delivered < 0 || $delivered > $requested) {
                throw new \InvalidArgumentException('الكمية المُسلَّمة لكل لون يجب أن تكون بين صفر والكمية المطلوبة');
            }

            $quantity->requested_quantity = $requested;
            $quantity->delivered_quantity = $delivered;
        });

        // The item's aggregate delivered total is always derived from these
        // authoritative color rows, whichever domain mutates them.
        static::saved(function (self $quantity) {
            $quantity->orderItem?->syncDeliveredAggregate();
        });

        static::deleted(function (self $quantity) {
            $quantity->orderItem?->syncDeliveredAggregate();
        });
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Derived, never stored: requested_quantity - delivered_quantity.
     */
    protected function remainingQuantity(): Attribute
    {
        return Attribute::get(
            fn (): int => max(0, (int) $this->requested_quantity - (int) $this->delivered_quantity),
        );
    }

    /**
     * Colors that still require production.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereColumn(
            'order_item_color_quantities.delivered_quantity',
            '<',
            'order_item_color_quantities.requested_quantity',
        );
    }

    /**
     * Colors that were fully delivered.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereColumn(
            'order_item_color_quantities.delivered_quantity',
            '>=',
            'order_item_color_quantities.requested_quantity',
        );
    }

    /**
     * Color rows whose order is in production (confirmed or partially
     * delivered). These are the only colors that count as current
     * production requirements.
     */
    public function scopeForProductionOrders(Builder $query): Builder
    {
        return $query->whereHas(
            'orderItem.order',
            fn (Builder $orderQuery) => $orderQuery->whereIn('status', OrderItem::productionStatuses()),
        );
    }

    /**
     * Pending color requirement rows, optionally for one product, with the
     * order-item context needed by the drill-down table and the Excel
     * export. One row represents exactly one ordered color.
     */
    public static function outstandingForProduction(?int $productId = null): Builder
    {
        return static::query()
            ->outstanding()
            ->forProductionOrders()
            ->join('order_items', 'order_items.id', '=', 'order_item_color_quantities.order_item_id')
            ->when(
                $productId !== null,
                fn (Builder $query) => $query->where('order_items.product_id', $productId),
            )
            ->select([
                'order_item_color_quantities.*',
                'order_items.order_id',
                'order_items.product_code',
                'order_items.product_name',
                'order_items.size',
            ])
            ->with([
                'orderItem.order:id,order_number,status,customer_id,created_at',
                'orderItem.order.customer:id,name,phone,customer_code',
            ]);
    }

    /**
     * Number of colors still requiring production, optionally for one
     * product. This is the production-requirements KPI: a sum of different
     * color requirements is not a meaningful manufactured quantity.
     */
    public static function outstandingColorCount(?int $productId = null): int
    {
        return static::query()
            ->outstanding()
            ->forProductionOrders()
            ->when(
                $productId !== null,
                fn (Builder $query) => $query->whereHas(
                    'orderItem',
                    fn (Builder $itemQuery) => $itemQuery->where('product_id', $productId),
                ),
            )
            ->count();
    }

    /**
     * Card data for the current production requirements: one entry per
     * product with its pending colors and each color's remaining quantity.
     * Colors with nothing remaining are never included.
     *
     * @return Collection<int, object{product_id: int, pending_colors: array<int, array{color: string, remaining: int}>, orders_count: int, uniform_remaining: int|null}>
     */
    public static function productionCards(?int $productId = null): Collection
    {
        $rows = static::query()
            ->outstanding()
            ->forProductionOrders()
            ->when(
                $productId !== null,
                fn (Builder $query) => $query->whereHas(
                    'orderItem',
                    fn (Builder $itemQuery) => $itemQuery->where('product_id', $productId),
                ),
            )
            ->join('order_items', 'order_items.id', '=', 'order_item_color_quantities.order_item_id')
            ->whereNotNull('order_items.product_id')
            ->orderBy('order_items.product_id')
            ->orderBy('order_item_color_quantities.id')
            ->get([
                'order_items.product_id',
                'order_items.order_id',
                'order_item_color_quantities.color',
                'order_item_color_quantities.requested_quantity',
                'order_item_color_quantities.delivered_quantity',
            ]);

        return $rows
            ->groupBy('product_id')
            ->map(function (Collection $productRows, int|string $groupedProductId): object {
                $pendingColors = [];
                $orderIds = [];

                foreach ($productRows as $row) {
                    /** @var self $row */
                    $remaining = max(0, (int) $row->requested_quantity - (int) $row->delivered_quantity);

                    if ($remaining < 1) {
                        continue;
                    }

                    $orderIds[(int) $row->order_id] = true;

                    $pendingColors[$row->color] = ($pendingColors[$row->color] ?? 0) + $remaining;
                }

                $colors = collect($pendingColors)
                    ->map(fn (int $remaining, int|string $color): array => [
                        'color' => (string) $color,
                        'remaining' => $remaining,
                    ])
                    ->values()
                    ->all();

                $quantities = array_column($colors, 'remaining');

                return (object) [
                    'product_id' => (int) $groupedProductId,
                    'pending_colors' => $colors,
                    'orders_count' => count($orderIds),
                    'uniform_remaining' => $quantities !== [] && count(array_unique($quantities)) === 1
                        ? (int) $quantities[0]
                        : null,
                ];
            })
            ->sortByDesc(fn (object $card): int => count($card->pending_colors))
            ->values();
    }

    /**
     * Controlled mutation point for a single color's delivered quantity.
     */
    public function setDeliveredQuantity(int $delivered): void
    {
        if ($delivered < 0 || $delivered > (int) $this->requested_quantity) {
            throw new \InvalidArgumentException('الكمية المُسلَّمة لكل لون يجب أن تكون بين صفر والكمية المطلوبة');
        }

        $this->delivered_quantity = $delivered;
        $this->save();
    }
}
