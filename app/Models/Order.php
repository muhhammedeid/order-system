<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'status',
        'customer_notes',
        'admin_notes',
        'total_quantity',
    ];

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
}
