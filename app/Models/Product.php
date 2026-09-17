<?php

namespace App\Models;

use App\Enums\PriceVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_code',
        'category_id',
        'name',
        'slug',
        'description',
        'price_visibility',
        'price',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'price_visibility' => PriceVisibility::class,
            'price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
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
                'product_code' => ['required', 'string', 'max:255', 'unique:products,product_code' . ($product ? ',' . $product->getKey() : '')],
                'slug' => ['required', 'string', 'max:255', 'unique:products,slug' . ($product ? ',' . $product->getKey() : '')],
                'price_visibility' => ['required', 'in:' . implode(',', array_column(PriceVisibility::cases(), 'value'))],
                'price' => self::priceRules(),
            ],
        )->validate();
    }
}
