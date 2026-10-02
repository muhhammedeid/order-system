<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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
     * WhatsApp messages explicitly associated with this order. Conversations
     * stay customer-scoped; only messages sent from the order panel carry an
     * order_id, so general history is never attributed to an order.
     */
    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'order_id');
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
                $requestedQuantity = $row['requested_quantity'] ?? $row['quantity'] ?? null;

                if ($itemId !== null) {
                    if (in_array($itemId, $keptIds, true)) {
                        throw new OrderItemException('لا يمكن تكرار نفس البند في الطلب');
                    }

                    if (! $currentItems->has($itemId)) {
                        throw new OrderItemException('أحد بنود الطلب غير موجود ضمن هذا الطلب');
                    }

                    $keptIds[] = $itemId;
                }

                if (! is_numeric($requestedQuantity)
                    || (int) $requestedQuantity != $requestedQuantity
                    || (int) $requestedQuantity < 1) {
                    throw new OrderItemException('الكمية لكل لون يجب أن تكون عددًا صحيحًا أكبر من صفر');
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
                    $colorCount = max(1, (int) $existing->color_count);

                    if ((int) $requestedQuantity > intdiv(OrderItem::MAX_QUANTITY, $colorCount)) {
                        throw new OrderItemException('إجمالي عدد القطع يتجاوز الحد المسموح');
                    }
                    if (! $variant->product->size_enabled
                        && (int) $requestedQuantity % ProductVariant::DEFAULT_SIZE_COUNT !== 0) {
                        throw new OrderItemException('الكمية لكل لون يجب أن تقبل القسمة على عدد المقاسات الافتراضية (5)');
                    }

                    $attributes = [
                        'requested_quantity' => (int) $requestedQuantity,
                        'quantity' => OrderItem::physicalQuantity((int) $requestedQuantity, $colorCount),
                    ];
                } else {
                    if (! $variant->product->size_enabled
                        && (int) $requestedQuantity % ProductVariant::DEFAULT_SIZE_COUNT !== 0) {
                        throw new OrderItemException('الكمية لكل لون يجب أن تقبل القسمة على عدد المقاسات الافتراضية (5)');
                    }

                    $attributes = OrderItem::snapshotFromVariant($variant) + [
                        'requested_quantity' => (int) $requestedQuantity,
                        'color_count' => 1,
                        'quantity' => (int) $requestedQuantity,
                    ];
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
     * Records partial deliveries for individual ordered colors. Submitted
     * quantities are the pieces delivered now for one color row and are
     * validated against that color's own remaining quantity; the aggregate
     * order-item delivered total is then synchronized from the color rows.
     *
     * @param  array<int|string, array{quantity: mixed, expected_delivered: mixed}>  $deliveries  keyed by color-quantity id
     */
    public function recordDeliveries(array $deliveries): void
    {
        DB::transaction(function () use ($deliveries) {
            $order = $this->lockForDelivery();

            $this->applyColorDeliveries($order, $deliveries);
        });

        $this->refresh();
        $this->unsetRelation('items');
    }

    /**
     * Marks every remaining color quantity as delivered. Remaining values
     * are computed only after the order, its items and their color rows are
     * locked inside the mutation transaction; the client never supplies
     * totals.
     */
    public function deliverAllRemaining(): void
    {
        DB::transaction(function () {
            $order = $this->lockForDelivery();

            $this->applyDeliverAll($order);
        });

        $this->refresh();
        $this->unsetRelation('items');
    }

    /**
     * Total delivered pieces that the historical migration could not
     * attribute to a color. Partial delivery stays blocked while it is
     * greater than zero.
     */
    public function hasUnallocatedDeliveries(): bool
    {
        return $this->items()->where('unallocated_delivered_quantity', '>', 0)->exists();
    }

    /**
     * True when at least one ordered color of this order still requires
     * delivery. The partial-delivery action is only offered in that case.
     */
    public function hasOutstandingColors(): bool
    {
        return OrderItemColorQuantity::query()
            ->whereIn('order_item_id', $this->items()->select('id'))
            ->outstanding()
            ->exists();
    }

    /**
     * One-time allocation of the legacy unallocated delivered quantities to
     * the exact colors that received them. The physical totals stay
     * unchanged; only their color attribution is recorded. Every submitted
     * item allocation is validated before anything is written, so a wrong
     * allocation never leaves a partially reconciled order behind.
     *
     * @param  array<int|string, mixed>  $allocations  keyed by color row id
     */
    public function reconcileUnallocatedDeliveries(array $allocations): void
    {
        DB::transaction(function () use ($allocations): void {
            $order = $this->lockForDelivery();

            $items = $order->items()->lockForUpdate()->get()->keyBy('id');

            $colorRows = OrderItemColorQuantity::query()
                ->whereIn('order_item_id', $items->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $prepared = [];
            $totalsPerItem = [];

            foreach ($allocations as $colorRowId => $amount) {
                $colorRow = $colorRows->get((int) $colorRowId);

                if (! $colorRow) {
                    throw new OrderDeliveryException('أحد ألوان بنود الطلب غير موجود ضمن هذا الطلب');
                }

                if (! is_numeric($amount) || (int) $amount != $amount || (int) $amount < 0) {
                    throw new OrderDeliveryException('الكمية الموزّعة لكل لون يجب أن تكون عددًا صحيحًا غير سالب');
                }

                $amount = (int) $amount;

                if ($amount > (int) $colorRow->remaining_quantity) {
                    throw new OrderDeliveryException(
                        "الكمية الموزّعة تتجاوز المتبقي للون {$colorRow->color} — المتاح: {$colorRow->remaining_quantity}"
                    );
                }

                $itemId = (int) $colorRow->order_item_id;
                $totalsPerItem[$itemId] = ($totalsPerItem[$itemId] ?? 0) + $amount;

                if ($amount > 0) {
                    $prepared[] = ['row' => $colorRow, 'amount' => $amount];
                }
            }

            foreach ($totalsPerItem as $itemId => $total) {
                $item = $items->get($itemId);
                $unallocated = (int) ($item?->unallocated_delivered_quantity ?? 0);

                if ($unallocated < 1) {
                    throw new OrderDeliveryException('لا توجد كميات تسليم غير موزّعة على الألوان لهذا البند');
                }

                if ($total !== $unallocated) {
                    throw new OrderDeliveryException("يجب توزيع كامل الكمية غير الموزّعة ({$unallocated}) على الألوان المحددة");
                }
            }

            // Clear the legacy remainders first so the authoritative color
            // rows become the only source of the delivered aggregates.
            foreach ($totalsPerItem as $itemId => $total) {
                $items->get($itemId)?->forceFill(['unallocated_delivered_quantity' => 0])->save();
            }

            foreach ($prepared as $entry) {
                /** @var OrderItemColorQuantity $colorRow */
                $colorRow = $entry['row'];
                $colorRow->setDeliveredQuantity((int) $colorRow->delivered_quantity + (int) $entry['amount']);
            }

            foreach ($items->whereIn('id', array_keys($totalsPerItem)) as $item) {
                $item->refresh()->syncDeliveredAggregate();
            }

            $order->refreshDeliveryStatus();
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
     * Validates and applies per-color delivery deltas against the locked
     * color rows, synchronizes the affected order-item totals and derives
     * the resulting order status. Throws and rolls back the surrounding
     * transaction on any invalid entry.
     *
     * @param  array<int|string, array{quantity: mixed, expected_delivered: mixed}>  $deliveries
     */
    private function applyColorDeliveries(self $order, array $deliveries): void
    {
        if ($deliveries === []) {
            throw new OrderDeliveryException('يجب تسجيل تسليم وحدة واحدة على الأقل');
        }

        $items = $order->items()->lockForUpdate()->get()->keyBy('id');

        $this->guardAgainstUnallocatedDeliveries($items);

        $colorRows = OrderItemColorQuantity::query()
            ->whereIn('order_item_id', $items->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $prepared = [];
        $total = 0;

        foreach ($deliveries as $colorRowId => $row) {
            $colorRow = $colorRows->get((int) $colorRowId);

            if (! $colorRow) {
                throw new OrderDeliveryException('أحد ألوان بنود الطلب غير موجود ضمن هذا الطلب');
            }

            $quantity = $row['quantity'] ?? null;
            $expected = $row['expected_delivered'] ?? null;

            if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 0) {
                throw new OrderDeliveryException('كمية التسليم للون يجب أن تكون عددًا صحيحًا غير سالب');
            }

            if (! is_numeric($expected) || (int) $expected != $expected || (int) $expected < 0) {
                throw new OrderDeliveryException('تعذر التحقق من حالة التسليم الحالية — يرجى إعادة فتح النافذة والمحاولة مرة أخرى');
            }

            if ((int) $colorRow->delivered_quantity !== (int) $expected) {
                throw new OrderDeliveryException('تم تحديث كميات التسليم من جلسة أخرى — يرجى إعادة فتح النافذة والمحاولة مرة أخرى');
            }

            $quantity = (int) $quantity;

            if ($quantity > (int) $colorRow->remaining_quantity) {
                throw new OrderDeliveryException(
                    "كمية التسليم تتجاوز المتبقي للون {$colorRow->color} — المتاح: {$colorRow->remaining_quantity}"
                );
            }

            $total += $quantity;
            $prepared[] = ['row' => $colorRow, 'quantity' => $quantity];
        }

        if ($total < 1) {
            throw new OrderDeliveryException('يجب تسجيل تسليم وحدة واحدة على الأقل');
        }

        $touchedItemIds = [];

        foreach ($prepared as $entry) {
            /** @var OrderItemColorQuantity $colorRow */
            $colorRow = $entry['row'];
            // Saving a color row synchronizes its order item's aggregate.
            $colorRow->setDeliveredQuantity((int) $colorRow->delivered_quantity + (int) $entry['quantity']);
            $touchedItemIds[(int) $colorRow->order_item_id] = true;
        }

        $order->refreshDeliveryStatus();
    }

    /**
     * Marks every outstanding color quantity as delivered in one guarded
     * transaction.
     */
    private function applyDeliverAll(self $order): void
    {
        $items = $order->items()->lockForUpdate()->get();

        $this->guardAgainstUnallocatedDeliveries($items);

        $colorRows = OrderItemColorQuantity::query()
            ->whereIn('order_item_id', $items->pluck('id'))
            ->lockForUpdate()
            ->get()
            ->filter(fn (OrderItemColorQuantity $row): bool => $row->remaining_quantity > 0);

        if ($colorRows->isEmpty()) {
            throw new OrderDeliveryException('لا توجد كميات متبقية للتسليم');
        }

        foreach ($colorRows as $colorRow) {
            $colorRow->setDeliveredQuantity((int) $colorRow->requested_quantity);
        }

        $order->refreshDeliveryStatus();
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function guardAgainstUnallocatedDeliveries($items): void
    {
        if ($items->contains(fn (OrderItem $item): bool => $item->hasUnallocatedDeliveries())) {
            throw new OrderDeliveryException(
                'يوجد تسليم غير موزّع على الألوان في هذا الطلب — يلزم إجراء تسوية التسليمات قبل تسجيل تسليم جديد'
            );
        }
    }

    /**
     * Re-derives and persists the delivery status after color-level
     * mutations: the aggregate item quantities still decide between
     * confirmed, partially delivered and delivered.
     */
    public function refreshDeliveryStatus(): void
    {
        $this->unsetRelation('items');
        $this->load('items');

        $status = $this->deriveDeliveryStatus() ?? OrderStatus::Confirmed;

        if ($this->status !== $status) {
            $this->status = $status;
            $this->save();
        }
    }

    /**
     * Derives the delivery status from the current item aggregates and the
     * authoritative color rows: null when nothing is delivered yet (status
     * stays `confirmed`), `partially_delivered` when some quantity was
     * delivered and something still remains, `delivered` only when nothing
     * remains at item level and no ordered color is still outstanding.
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

        if ($remaining > 0) {
            return OrderStatus::PartiallyDelivered;
        }

        $outstandingColors = OrderItemColorQuantity::query()
            ->whereIn('order_item_id', $items->pluck('id'))
            ->outstanding()
            ->exists();

        return $outstandingColors ? OrderStatus::PartiallyDelivered : OrderStatus::Delivered;
    }
}
