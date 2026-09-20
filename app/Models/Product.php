<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PriceVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use HasFactory;

    protected $attributes = [
        'size_enabled' => false,
        'color_enabled' => true,
    ];

    protected $fillable = [
        'product_code',
        'category_id',
        'name',
        'slug',
        'description',
        'price_visibility',
        'price',
        'active',
        'color_enabled',
        'size_enabled',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'price_visibility' => PriceVisibility::class,
            'price' => 'decimal:2',
            'active' => 'boolean',
            'color_enabled' => 'boolean',
            'size_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            $visibility = $product->price_visibility ?? PriceVisibility::PublicPrice;

            if ($visibility === PriceVisibility::PublicPrice && blank($product->price)) {
                throw ValidationException::withMessages([
                    'price' => 'Price مطلوب للمنتج بسعر معلن',
                ]);
            }

            if (! $product->color_enabled && $product->size_enabled) {
                throw ValidationException::withMessages([
                    'size_enabled' => 'لا يمكن تفعيل اختيار المقاس إلا عند تفعيل اختيار اللون.',
                ]);
            }
        });

        static::deleting(function (self $product) {
            if ($product->isReferencedByActiveOrder()) {
                throw new \RuntimeException('لا يمكن حذف المنتج لأن بعض مقاساته مرتبطة بطلبات نشطة — يمكن تعطيل المنتج بدلًا من حذفه');
            }
        });

    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function isReferencedByActiveOrder(): bool
    {
        return $this->variants()
            ->whereHas('orderItems.order', fn ($query) => $query->whereIn('status', OrderStatus::activeValues()))
            ->exists();
    }

    public static function priceRules(): array
    {
        return [
            'nullable',
            'numeric',
            'min:0',
            'required_if:price_visibility,public',
        ];
    }

    public static function validate(array $data, ?self $product = null): void
    {
        $validator = Validator::make(
            $data,
            [
                'product_code' => ['required', 'string', 'max:255', 'unique:products,product_code'.($product ? ','.$product->getKey() : '')],
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'unique:products,slug'.($product ? ','.$product->getKey() : '')],
                'price_visibility' => ['required', 'in:'.implode(',', array_column(PriceVisibility::cases(), 'value'))],
                'price' => self::priceRules(),
                'color_enabled' => ['boolean'],
                'size_enabled' => ['boolean'],
            ],
        );

        $validator->after(function ($validator) use ($data): void {
            if (! (bool) ($data['color_enabled'] ?? true) && (bool) ($data['size_enabled'] ?? false)) {
                $validator->errors()->add(
                    'size_enabled',
                    'لا يمكن تفعيل اختيار المقاس إلا عند تفعيل اختيار اللون.',
                );
            }
        });

        $validator->validate();
    }
}
