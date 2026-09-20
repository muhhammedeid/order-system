<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_notes',
        'admin_notes',
        'total_quantity',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_quantity' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Candidate order number for the current year (ORD-YYYY-NNNNN).
     * Collision safety is guaranteed by the unique(order_number)
     * constraint; the caller retries on collision.
     */
    public static function nextOrderNumber(): string
    {
        $prefix = 'ORD-'.date('Y').'-';

        $max = static::query()
            ->where('order_number', 'like', $prefix.'%')
            ->max('order_number');

        $sequence = $max === null ? 0 : (int) substr($max, strlen($prefix));

        return $prefix.str_pad((string) ($sequence + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Trusted per-status counts in one grouped query, used by the Admin
     * operational tabs and navigation badge.
     *
     * @return array<string, int>
     */
    public static function statusCounts(): array
    {
        $counts = static::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (OrderStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * Only `new` orders may be edited before confirmation. Once confirmed,
     * delivered quantities are the controlled mutation path (R02).
     */
    public function isEditable(): bool
    {
        return $this->status === OrderStatus::New;
    }

    /**
     * Orders that are confirmed and still in execution: fully confirmed
     * with nothing delivered yet, or partially delivered with a remaining
     * quantity. Fully delivered and cancelled orders are excluded.
     */
    public static function confirmedInExecutionCount(): int
    {
        return static::query()
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::PartiallyDelivered->value])
            ->count();
    }

    /**
     * Recalculates and persists the derived total quantity from the
     * current order items. Must be called after any allowed item edit.
     */
    public function recalculateTotalQuantity(): void
    {
        $this->total_quantity = (int) $this->items()->sum('quantity');
        $this->save();
    }

    /**
     * new → confirmed. No stock effect: available_quantity is an
     * Admin-maintained reference and never part of the order lifecycle.
     */
    public function confirm(): void
    {
        DB::transaction(function () {
            /** @var self|null $order */
            $order = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== OrderStatus::New) {
                throw OrderTransitionException::forStatus($order?->status ?? null);
            }

            $order->status = OrderStatus::Confirmed;
            $order->save();
        });
    }

    /**
     * new → cancelled (terminal). Confirmed orders are in execution and
     * cannot be cancelled under the approved workflow.
     */
    public function cancel(): void
    {
        DB::transaction(function () {
            /** @var self|null $order */
            $order = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== OrderStatus::New) {
                throw OrderTransitionException::forStatus($order?->status ?? null);
            }

            $order->status = OrderStatus::Cancelled;
            $order->save();
        });
    }

    /**
     * Replaces the item set of a `new` order from an untrusted payload.
     *
     * Row shape: [['id' => ?int, 'product_id' => ?int, 'product_variant_id' => int, 'quantity' => int], ...]
     *
     * - only existing item ids that belong to this order are accepted;
     *   duplicate or unknown ids are rejected;
     * - the variant is the source of truth; a submitted product id must match it;
     * - snapshots are preserved for unchanged variants and rebuilt from trusted
     *   product/variant data for changed or new items;
     * - delivered_quantity is never accepted from the payload.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function updateItems(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            /** @var self|null $order */
            $order = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== OrderStatus::New) {
                throw new OrderTransitionException('لا يمكن تعديل بنود الطلب بعد تأكيده أو إلغائه');
            }

            if ($rows === []) {
                throw new OrderItemException('يجب أن يحتوي الطلب على بند واحد على الأقل');
            }

            $currentItems = $order->items()->lockForUpdate()->get()->keyBy('id');
            $keptIds = [];
            $prepared = [];

            foreach ($rows as $row) {
                $itemId = isset($row['id']) && filled($row['id']) ? (int) $row['id'] : null;
                $variantId = isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : 0;
                $quantity = $row['quantity'] ?? null;

                if ($itemId !== null) {
                    if (in_array($itemId, $keptIds, true)) {
                        throw new OrderItemException('لا يمكن تكرار نفس البند في الطلب');
                    }

                    if (! $currentItems->has($itemId)) {
                        throw new OrderItemException('أحد بنود الطلب غير موجود ضمن هذا الطلب');
                    }

                    $keptIds[] = $itemId;
                }

                if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1) {
                    throw new OrderItemException('كمية البند يجب أن تكون عددًا صحيحًا أكبر من صفر');
                }

                if ($variantId <= 0) {
                    throw new OrderItemException('يجب اختيار اللون/المقاس لكل بند');
                }

                $variant = ProductVariant::query()->with('product')->find($variantId);

                if (! $variant || ! $variant->product) {
                    throw new OrderItemException('اللون/المقاس المحدد لم يعد موجودًا');
                }

                if (isset($row['product_id']) && filled($row['product_id']) && (int) $row['product_id'] !== (int) $variant->product_id) {
                    throw new OrderItemException('المنتج المحدد لا يطابق اللون/المقاس المختار');
                }

                $existing = $itemId !== null ? $currentItems->get($itemId) : null;

                if ($existing && (int) $existing->product_variant_id === $variantId) {
                    // Quantity-only edit: preserve the accepted snapshots.
                    $attributes = ['quantity' => (int) $quantity];
                } else {
                    $attributes = OrderItem::snapshotFromVariant($variant) + ['quantity' => (int) $quantity];
                }

                $prepared[] = ['item' => $existing, 'attributes' => $attributes];
            }

            $removedIds = $currentItems->keys()->diff($keptIds);

            if ($removedIds->isNotEmpty()) {
                $order->items()->whereIn('id', $removedIds)->delete();
            }

            foreach ($prepared as $entry) {
                if ($entry['item']) {
                    $entry['item']->fill($entry['attributes']);
                    $entry['item']->save();
                } else {
                    $order->items()->create($entry['attributes']);
                }
            }

            $order->total_quantity = (int) $order->items()->sum('quantity');
            $order->save();
        });

        $this->refresh();
        $this->unsetRelation('items');
    }

    /**
     * Records partial deliveries for the given items.
     *
     * @param  array<int, array{quantity: mixed, expected_delivered: mixed}>  $deliveries  keyed by order_item id
     */
    public function recordDeliveries(array $deliveries): void
    {
        DB::transaction(function () use ($deliveries) {
            $order = $this->lockForDelivery();

            $this->applyDeliveries($order, $deliveries);
        });

        $this->refresh();
        $this->unsetRelation('items');
    }

    /**
     * Marks every remaining quantity as delivered. Remaining values are
     * computed only after the order and its items are locked inside the
     * mutation transaction; the client never supplies totals.
     */
    public function deliverAllRemaining(): void
    {
        DB::transaction(function () {
            $order = $this->lockForDelivery();

            $this->applyDeliveries($order, [], deliverAll: true);
        });

        $this->refresh();
        $this->unsetRelation('items');
    }

    /**
     * Fresh locked order with a valid delivery source status.
     */
    private function lockForDelivery(): self
    {
        /** @var self|null $order */
        $order = static::query()
            ->whereKey($this->getKey())
            ->lockForUpdate()
            ->first();

        if (! $order || ! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::PartiallyDelivered], true)) {
            throw OrderTransitionException::forStatus($order?->status ?? null);
        }

        return $order;
    }

    /**
     * Validates and applies delivery deltas against the locked item set,
     * then derives the resulting order status. Throws and rolls back the
     * surrounding transaction on any invalid entry.
     *
     * @param  array<int, array{quantity: mixed, expected_delivered: mixed}>  $deliveries
     */
    private function applyDeliveries(self $order, array $deliveries, bool $deliverAll = false): void
    {
        $items = $order->items()->lockForUpdate()->get()->keyBy('id');

        if ($deliverAll) {
            $deliveries = [];

            foreach ($items as $item) {
                $remaining = (int) $item->remaining_quantity;

                if ($remaining > 0) {
                    $deliveries[$item->id] = [
                        'quantity' => $remaining,
                        'expected_delivered' => (int) $item->delivered_quantity,
                    ];
                }
            }

            if ($deliveries === []) {
                throw new OrderDeliveryException('لا توجد كميات متبقية للتسليم');
            }
        }

        if ($deliveries === []) {
            throw new OrderDeliveryException('يجب تسجيل تسليم وحدة واحدة على الأقل');
        }

        $prepared = [];
        $total = 0;

        foreach ($deliveries as $itemId => $row) {
            $item = $items->get((int) $itemId);

            if (! $item) {
                throw new OrderDeliveryException('أحد بنود الطلب غير موجود ضمن هذا الطلب');
            }

            $quantity = $row['quantity'] ?? null;
            $expected = $row['expected_delivered'] ?? null;

            if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 0) {
                throw new OrderDeliveryException('كمية التسليم يجب أن تكون عددًا صحيحًا غير سالب');
            }

            if (! is_numeric($expected) || (int) $expected != $expected || (int) $expected < 0) {
                throw new OrderDeliveryException('تعذر التحقق من حالة التسليم الحالية — يرجى إعادة فتح النافذة والمحاولة مرة أخرى');
            }

            if ((int) $item->delivered_quantity !== (int) $expected) {
                throw new OrderDeliveryException('تم تحديث كميات التسليم من جلسة أخرى — يرجى إعادة فتح النافذة والمحاولة مرة أخرى');
            }

            if ((int) $quantity > (int) $item->remaining_quantity) {
                throw new OrderDeliveryException("كمية التسليم تتجاوز المتبقي للبند {$item->product_name} ({$item->product_code}) — المتبقي {$item->remaining_quantity}");
            }

            $total += (int) $quantity;
            $prepared[] = ['item' => $item, 'quantity' => (int) $quantity];
        }

        if ($total < 1) {
            throw new OrderDeliveryException('يجب تسجيل تسليم وحدة واحدة على الأقل');
        }

        foreach ($prepared as $entry) {
            $entry['item']->setDeliveredQuantity((int) $entry['item']->delivered_quantity + $entry['quantity']);
        }

        $order->unsetRelation('items');
        $order->load('items');

        $status = $order->deriveDeliveryStatus() ?? OrderStatus::Confirmed;

        if ($order->status !== $status) {
            $order->status = $status;
            $order->save();
        }
    }

    /**
     * Derives the delivery status from current item delivered quantities:
     * null when nothing is delivered yet (status stays `confirmed`),
     * `partially_delivered` when some quantity was delivered and some
     * remains, `delivered` when nothing remains.
     */
    public function deriveDeliveryStatus(): ?OrderStatus
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        if ($items->isEmpty()) {
            return null;
        }

        $delivered = $items->sum(fn (OrderItem $item) => (int) $item->delivered_quantity);
        $remaining = $items->sum(fn (OrderItem $item) => (int) $item->remaining_quantity);

        if ($delivered === 0) {
            return null;
        }

        return $remaining === 0 ? OrderStatus::Delivered : OrderStatus::PartiallyDelivered;
    }
}
