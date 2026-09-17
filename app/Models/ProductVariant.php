<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function casts(): array
    {
        return [
            'available_quantity' => 'integer',
        ];
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
