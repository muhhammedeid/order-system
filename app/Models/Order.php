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
        $prefix = 'ORD-' . date('Y') . '-';

        $max = static::query()
            ->where('order_number', 'like', $prefix . '%')
            ->max('order_number');

        $sequence = $max === null ? 0 : (int) substr($max, strlen($prefix));

        return $prefix . str_pad((string) ($sequence + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * new → confirmed: revalidates against current availability with row
     * locks and decrements each ordered variant exactly once.
     */
    public function confirm(): void
    {
        DB::transaction(function () {
            /** @var self $order */
            $order = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== OrderStatus::New) {
                throw OrderTransitionException::forStatus($order?->status ?? null);
            }

            $items = $order->items()->with('productVariant')->lockForUpdate()->get();

            foreach ($items as $item) {
                if (! $item->product_variant_id) {
                    throw new OrderTransitionException(
                        "البند {$item->product_name} ({$item->product_code} / {$item->color} / {$item->size}) غير مرتبط بمقاس محدد، لا يمكن تأكيد الطلب"
                    );
                }
            }

            $variants = \App\Models\ProductVariant::query()
                ->whereIn('id', $items->pluck('product_variant_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variant = $variants->get($item->product_variant_id);

                if (! $variant) {
                    throw new OrderTransitionException(
                        "المقاس المطلوب للبند {$item->product_name} ({$item->product_code} / {$item->color} / {$item->size}) لم يعد متوفرًا"
                    );
                }

                if ($item->quantity > $variant->available_quantity) {
                    throw new OrderTransitionException(
                        "الكمية المطلوبة غير متوفرة: {$item->product_name} ({$item->product_code} / {$item->color} / {$item->size}) — المتاح {$variant->available_quantity} والمطلوب {$item->quantity}"
                    );
                }
            }

            foreach ($items as $item) {
                \App\Models\ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->decrement('available_quantity', $item->quantity);
            }

            $order->status = OrderStatus::Confirmed;
            $order->save();
        });
    }

    /**
     * new → cancelled (no quantity effect) or confirmed → cancelled
     * (restores previously deducted quantities exactly once).
     */
    public function cancel(): void
    {
        DB::transaction(function () {
            /** @var self $order */
            $order = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || ! in_array($order->status, [OrderStatus::New, OrderStatus::Confirmed], true)) {
                throw OrderTransitionException::forStatus($order?->status ?? null);
            }

            if ($order->status === OrderStatus::Confirmed) {
                $items = $order->items()->lockForUpdate()->get();

                foreach ($items as $item) {
                    if (! $item->product_variant_id) {
                        throw new OrderTransitionException(
                            "البند {$item->product_name} ({$item->product_code} / {$item->color} / {$item->size}) غير مرتبط بمقاس محدد، لا يمكن استعادة الكمية"
                        );
                    }
                }

                foreach ($items as $item) {
                    \App\Models\ProductVariant::query()
                        ->whereKey($item->product_variant_id)
                        ->increment('available_quantity', $item->quantity);
                }
            }

            $order->status = OrderStatus::Cancelled;
            $order->save();
        });
    }

    /**
     * confirmed → exported (terminal, no quantity effect).
     */
    public function markExported(): void
    {
        DB::transaction(function () {
            /** @var self $order */
            $order = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== OrderStatus::Confirmed) {
                throw OrderTransitionException::forStatus($order?->status ?? null);
            }

            $order->status = OrderStatus::Exported;
            $order->save();
        });
    }
}
