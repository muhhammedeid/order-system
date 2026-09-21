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
            $item->quantity = $requestedQuantity * $colorCount;

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
     * Restricts the query to items with a positive remaining quantity
     * (quantity - delivered_quantity).
     */
    public function scopeWithOutstandingQuantity(Builder $query): Builder
    {
        return $query->whereColumn('order_items.delivered_quantity', '<', 'order_items.quantity');
    }

    /**
     * Total remaining quantity for production, optionally for one product.
     * Rows in excluded statuses or with zero remaining contribute nothing.
     */
    public static function outstandingQuantityTotal(?int $productId = null): int
    {
        return (int) static::query()
            ->inProduction()
            ->withOutstandingQuantity()
            ->when($productId !== null, fn (Builder $query) => $query->where('order_items.product_id', $productId))
            ->sum(DB::raw('order_items.quantity - order_items.delivered_quantity'));
    }

    /**
     * Outstanding quantity grouped by product, used by the dashboard
     * production-requirements catalog. Products with no positive
     * outstanding quantity are excluded.
     *
     * @return Collection<int, object{product_id: int, required_quantity: int, requested_quantity: int, orders_count: int}>
     */
    public static function productProductionTotals(): Collection
    {
        return static::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::productionStatuses())
            ->whereColumn('order_items.delivered_quantity', '<', 'order_items.quantity')
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id as product_id')
            ->selectRaw('SUM(order_items.quantity - order_items.delivered_quantity) as required_quantity')
            ->selectRaw('SUM(CASE WHEN order_items.requested_quantity IS NULL OR order_items.requested_quantity < 1 THEN order_items.quantity ELSE order_items.requested_quantity END) as requested_quantity')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders_count')
            ->havingRaw('SUM(order_items.quantity - order_items.delivered_quantity) > 0')
            ->orderByDesc('required_quantity')
            ->get();
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
