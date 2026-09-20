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
        });

        static::deleting(function (self $product) {
            if ($product->isReferencedByActiveOrder()) {
                throw new \RuntimeException('لا يمكن حذف المنتج لأن بعض مقاساته مرتبطة بطلبات نشطة — يمكن تعطيل المنتج بدلًا من حذفه');
            }
        });

        static::updating(function (self $product) {
            if ($product->isDirty('size_enabled')) {
                $hasSizedVariants = $product->variants()
                    ->whereRaw("TRIM(COALESCE(size, '')) <> ''")
                    ->exists();

                $hasUnsizedVariants = $product->variants()
                    ->whereRaw("TRIM(COALESCE(size, '')) = ''")
                    ->exists();

                if ($product->size_enabled === false && $hasSizedVariants) {
                    throw ValidationException::withMessages([
                        'size_enabled' => 'لا يمكن تعطيل المقاسات لأن هذا المنتج يحتوي على مقاسات مسجلة — يجب حل هذه المقاسات أو حذفها أولًا.',
                    ]);
                }

                if ($product->size_enabled === true && $hasUnsizedVariants) {
                    throw ValidationException::withMessages([
                        'size_enabled' => 'لا يمكن تفعيل المقاسات لأن هذا المنتج يحتوي على أصناف بدون مقاس — يجب تحديد مقاس لكل الأصناف أو حذفها أولًا.',
                    ]);
                }
            }

            if ($product->isDirty('color_enabled')) {
                $message = $product->colorEnabledConflictMessage((bool) $product->color_enabled);

                if ($message !== null) {
                    throw ValidationException::withMessages([
                        'color_enabled' => $message,
                    ]);
                }
            }
        });
    }

    /**
     * Friendly message for a requested `color_enabled` state that conflicts
     * with the product's current variants, or null when the state is allowed.
     * Shared by the model guard and the Admin form rule so both stay in sync.
     */
    public function colorEnabledConflictMessage(bool $enabled): ?string
    {
        if ($enabled === false && $this->hasColoredVariants()) {
            return 'لا يمكن تعطيل اختيار اللون لأن هذا المنتج يحتوي على ألوان مسجلة — يجب حل هذه الألوان أو حذفها أولًا.';
        }

        if ($enabled === true && $this->hasUncoloredVariants()) {
            return 'لا يمكن تفعيل اختيار اللون لأن هذا المنتج يحتوي على أصناف بدون لون — يجب تحديد لون لكل الأصناف أو حذفها أولًا.';
        }

        return null;
    }

    public function hasColoredVariants(): bool
    {
        return $this->variants()
            ->whereRaw("TRIM(COALESCE(color, '')) <> ''")
            ->exists();
    }

    public function hasUncoloredVariants(): bool
    {
        return $this->variants()
            ->whereRaw("TRIM(COALESCE(color, '')) = ''")
            ->exists();
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
        Validator::make(
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
        )->validate();
    }
}
