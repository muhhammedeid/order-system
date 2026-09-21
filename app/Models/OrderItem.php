<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PriceVisibility;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'unallocated_delivered_quantity' => 'integer',
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

        static::saved(function (self $item) {
            // Color-level rows mirror the immutable snapshot; they are the
            // authoritative source for delivery and production requirements.
            DB::transaction(fn () => $item->syncColorQuantities());
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Authoritative requested/delivered quantities for every ordered color.
     */
    public function colorQuantities(): HasMany
    {
        return $this->hasMany(OrderItemColorQuantity::class)->orderBy('id');
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
     * physical total stays well defined for historical rows.
     */
    public function effectiveColorCount(): int
    {
        return max(1, (int) $this->color_count);
    }

    /**
     * Individual color names included in this line. The snapshot stores a
     * human-readable list (Arabic or Latin commas) and is never rewritten
     * after the order was accepted, so product color changes cannot alter
     * an active order's production requirements.
     *
     * @return array<int, string>
     */
    public function snapshotColors(): array
    {
        $colors = preg_split('/[،,]+/u', (string) $this->color, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $unique = [];

        foreach ($colors as $color) {
            $color = trim($color);

            if ($color === '') {
                continue;
            }

            // The unique index is collation-dependent on MySQL/MariaDB, so
            // deduplicate the same way here.
            $unique[mb_strtolower($color)] ??= $color;
        }

        if ($unique !== []) {
            return array_values($unique);
        }

        $fallback = trim((string) $this->color);

        return [$fallback !== '' ? $fallback : 'غير محدد'];
    }

    /**
     * Converts a per-color quantity into the stored physical piece total.
     * The single conversion point for order editing and the derived
     * quantity invariant.
     */
    public static function physicalQuantity(int $quantityPerColor, int $colorCount): int
    {
        return $quantityPerColor * max(1, $colorCount);
    }

    /**
     * Ensures one color row per accepted snapshot color with the current
     * requested quantity, preserving every recorded delivery. Colors that
     * left the snapshot are only removed while nothing was delivered for
     * them; reducing a requested quantity below what was delivered is
     * rejected instead of silently discarding deliveries.
     */
    public function syncColorQuantities(): void
    {
        $requested = max(1, (int) $this->requested_quantity);

        $existing = $this->colorQuantities()
            ->get()
            ->keyBy(fn (OrderItemColorQuantity $row): string => mb_strtolower(trim($row->color)));

        foreach ($this->snapshotColors() as $color) {
            $row = $existing->pull(mb_strtolower(trim($color)));

            if (! $row) {
                $this->colorQuantities()->create([
                    'color' => $color,
                    'requested_quantity' => $requested,
                ]);

                continue;
            }

            if ((int) $row->delivered_quantity > $requested) {
                throw ValidationException::withMessages([
                    'requested_quantity' => 'لا يمكن تقليل الكمية المطلوبة إلى أقل من الكمية المُسلَّمة لكل لون',
                ]);
            }

            if ((int) $row->requested_quantity !== $requested) {
                $row->requested_quantity = $requested;
                $row->save();
            }
        }

        foreach ($existing as $removed) {
            if ((int) $removed->delivered_quantity > 0) {
                throw ValidationException::withMessages([
                    'color' => 'لا يمكن إزالة لون تم تسليم كميات منه',
                ]);
            }

            $removed->delete();
        }
    }

    /**
     * Keeps the physical delivered total aligned with the authoritative
     * color rows (including any legacy unallocated remainder). Only the
     * delivery and reconciliation domains may rely on this.
     */
    public function syncDeliveredAggregate(): void
    {
        $delivered = (int) $this->colorQuantities()->sum('delivered_quantity')
            + (int) $this->unallocated_delivered_quantity;

        if ($delivered > (int) $this->quantity) {
            throw new \InvalidArgumentException('الكمية المُسلَّمة يجب أن تكون بين صفر والكمية المطلوبة');
        }

        if ((int) $this->delivered_quantity !== $delivered) {
            $this->delivered_quantity = $delivered;
            $this->save();
        }
    }

    /**
     * True while historical delivered pieces could not be attributed to a
     * color. Partial delivery is blocked until they are reconciled.
     */
    public function hasUnallocatedDeliveries(): bool
    {
        return (int) $this->unallocated_delivered_quantity > 0;
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
}
