<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'color',
        'size',
        'available_quantity',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $variant) {
            $referencedByConfirmed = $variant->orderItems()
                ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Confirmed->value))
                ->exists();

            if ($referencedByConfirmed) {
                throw new \RuntimeException('لا يمكن حذف هذا المقاس لأنه مرتبط بطلب مؤكد — يمكن إلغاء الطلب أولًا');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'available_quantity' => 'integer',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function color(): Attribute
    {
        return Attribute::set(fn ($value) => trim((string) $value));
    }

    protected function size(): Attribute
    {
        return Attribute::set(fn ($value) => trim((string) $value));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function validate(array $data): array
    {
        $data['color'] = trim((string) ($data['color'] ?? ''));
        $data['size'] = trim((string) ($data['size'] ?? ''));

        Validator::make(
            $data,
            [
                'color' => ['required', 'string', 'max:255'],
                'size' => ['required', 'string', 'max:255'],
                'available_quantity' => ['required', 'integer', 'min:0'],
            ],
        )->validate();

        return $data;
    }
}
