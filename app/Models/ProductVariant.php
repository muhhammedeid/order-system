<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductVariant extends Model
{
    use HasFactory;

    /**
     * Technical upper bound shared by every quantity entry point
     * (unsigned INT column limit), mirroring OrderItem::MAX_QUANTITY.
     */
    public const MAX_QUANTITY = 4294967295;

    public const DEFAULT_SIZE_COUNT = 5;

    protected $fillable = [
        'product_id',
        'color',
        'size',
        'available_quantity',
    ];

    protected $hidden = [
        'color_key',
        'size_key',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $variant) {
            $color = trim((string) $variant->color);

            if ($color === '' && (! $variant->exists || $variant->isDirty('color'))) {
                throw ValidationException::withMessages([
                    'color' => 'اللون مطلوب لكل صنف حتى يظهر ضمن الألوان المتاحة للعميل.',
                ]);
            }
        });

        static::deleting(function (self $variant) {
            if ($variant->isReferencedByActiveOrder()) {
                throw new \RuntimeException('لا يمكن حذف هذا المقاس لأنه مرتبط بطلب نشط — يمكن إبقاؤه دون تغيير بدلًا من حذفه');
            }
        });
    }

    public function isReferencedByActiveOrder(): bool
    {
        return $this->orderItems()
            ->whereHas('order', fn ($query) => $query->whereIn('status', OrderStatus::activeValues()))
            ->exists();
    }

    public static function existsFor(Product $product, ?string $color, ?string $size, ?int $ignoreId = null): bool
    {
        $candidateKey = self::combinationKey($color, $size);

        return $product->variants()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'color', 'size'])
            ->contains(fn (self $variant): bool => self::combinationKey($variant->color, $variant->size) === $candidateKey);
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
        return Attribute::set(function ($value) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        });
    }

    protected function size(): Attribute
    {
        return Attribute::set(function ($value) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Availability data is independent from the storefront choice toggles:
     * every variant has a color, while size remains optional for size-less products.
     */
    public static function validate(array $data, ?Product $product = null): array
    {
        $data['color'] = trim((string) ($data['color'] ?? ''));
        $data['size'] = trim((string) ($data['size'] ?? ''));

        Validator::make(
            $data,
            [
                'color' => ['required', 'string', 'max:255'],
                'size' => ['nullable', 'string', 'max:255'],
                'available_quantity' => ['required', 'integer', 'min:0', 'max:'.self::MAX_QUANTITY],
            ],
        )->validate();

        if ($data['color'] === '') {
            $data['color'] = null;
        }

        if ($data['size'] === '') {
            $data['size'] = null;
        }

        return $data;
    }

    /**
     * Creates only the missing Product + Color + Size combinations for the
     * submitted color rows, copying each row's default quantity
     * into the new variants. Existing variants are never read back into state,
     * updated or deleted; the default quantity is a creation convenience only.
     *
     * The whole generation is atomic: every candidate is validated before the
     * first insert, and the transaction rolls back on any failure.
     *
     * @param  array<int, array{color?: mixed, quantity?: mixed}>  $colorRows
     * @return array{created: int, skipped: int}
     */
    public static function generateMissing(Product $product, array $colorRows): array
    {
        return DB::transaction(function () use ($product, $colorRows): array {
            /** @var Product $product */
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $sizes = self::generationSizes();

            if ($colorRows === []) {
                throw ValidationException::withMessages([
                    'colors' => 'أضف لونًا واحدًا على الأقل.',
                ]);
            }

            $existing = self::existingCombinationKeys($product);
            $pending = [];
            $skipped = 0;

            foreach ($colorRows as $row) {
                $color = trim((string) ($row['color'] ?? ''));
                $quantity = $row['quantity'] ?? null;

                foreach ($sizes as $size) {
                    $candidate = self::validate([
                        'color' => $color,
                        'size' => $size,
                        'available_quantity' => $quantity,
                    ], $product);

                    $key = self::combinationKey($candidate['color'], $candidate['size']);

                    if (isset($existing[$key]) || isset($pending[$key])) {
                        $skipped++;

                        continue;
                    }

                    $pending[$key] = $candidate;
                }
            }

            foreach ($pending as $candidate) {
                $variant = new self($candidate);
                $variant->product()->associate($product);
                $variant->save();
            }

            return ['created' => count($pending), 'skipped' => $skipped];
        });
    }

    /**
     * @return array<int, string | null>
     */
    protected static function generationSizes(): array
    {
        $activeSizes = VariantSize::activeNames();

        if (count($activeSizes) !== self::DEFAULT_SIZE_COUNT) {
            throw ValidationException::withMessages([
                'sizes' => 'يجب أن يكون هناك 5 مقاسات نشطة بالضبط قبل إضافة لون.',
            ]);
        }

        return $activeSizes;
    }

    /**
     * @return array<string, true>
     */
    protected static function existingCombinationKeys(Product $product): array
    {
        return $product->variants()
            ->get(['color', 'size'])
            ->mapWithKeys(fn (self $variant): array => [
                self::combinationKey($variant->color, $variant->size) => true,
            ])
            ->all();
    }

    protected static function combinationKey(?string $color, ?string $size): string
    {
        return mb_strtolower(trim((string) $color)).'|'.trim((string) $size);
    }
}
