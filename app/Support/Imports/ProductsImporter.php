<?php

namespace App\Support\Imports;

use App\Enums\PriceVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductsImporter
{
    public const REQUIRED_HEADERS = ['Product Code'];

    public const HEADERS = [
        'Product Code',
        'Product Name',
        'Category',
        'Price',
        'Price Visibility',
        'Active',
    ];

    public const TEMPLATE_HEADERS = [
        ...self::HEADERS,
        'Description',
        'Colors',
        'Sizes',
        'Color Enabled',
        'Size Enabled',
    ];

    public function process(array $keyedRows): ImportResult
    {
        $result = new ImportResult;
        $seenCodes = [];

        foreach ($keyedRows as $rowNumber => $row) {
            try {
                $status = DB::transaction(function () use ($row, &$seenCodes) {
                    return $this->processRow($row, $seenCodes);
                });

                $result->addRow($rowNumber, $status['status'], $status['reason'] ?? null);
            } catch (\Throwable $exception) {
                $result->addRow($rowNumber, 'invalid', $this->reason($exception));
            }
        }

        return $result;
    }

    private function processRow(array $row, array &$seenCodes): array
    {
        foreach ($row as $cell) {
            if (is_string($cell) && str_starts_with(ltrim($cell), '=')) {
                throw new \RuntimeException('لا تستخدم صيغ Excel في ملف الاستيراد.');
            }
        }
        $code = trim((string) ($row['Product Code'] ?? ''));

        if (blank($code)) {
            throw new \RuntimeException('الحقل Product Code مطلوب');
        }

        if (in_array($code, $seenCodes, true)) {
            return ['status' => 'invalid', 'reason' => "كود منتج مكرر داخل الملف: {$code}"];
        }

        $seenCodes[] = $code;

        $existing = Product::query()->where('product_code', $code)->first();
        $name = $this->provided($row, 'Product Name') ? trim((string) $row['Product Name']) : ($existing?->name ?? 'Mai '.$code);
        $visibility = $this->provided($row, 'Price Visibility') ? $this->parseVisibility($row) : ($existing?->price_visibility ?? PriceVisibility::RequestPrice);
        $active = $this->provided($row, 'Active') ? $this->parseActive($row) : ($existing?->active ?? true);
        $categoryId = $this->provided($row, 'Category') ? $this->resolveCategory($row)->id : $existing?->category_id;

        $priceCell = trim((string) ($row['Price'] ?? ''));
        $priceProvided = filled($priceCell);
        $price = null;

        if ($priceProvided) {
            $price = $this->numericPrice($priceCell);
        }

        $attributes = [
            'name' => $name,
            'category_id' => $categoryId,
            'price_visibility' => $visibility->value,
            'active' => $active,
        ];
        foreach (['Description' => 'description', 'Color Enabled' => 'color_enabled', 'Size Enabled' => 'size_enabled'] as $header => $attribute) {
            if ($this->provided($row, $header)) {
                $attributes[$attribute] = $header === 'Description' ? trim((string) $row[$header]) : $this->parseActive(['Active' => $row[$header]]);
            }
        }
        if ($priceProvided) {
            $attributes['price'] = $price;
        }
        if ($visibility === PriceVisibility::PublicPrice && ! $priceProvided && $existing?->price === null) {
            throw new \RuntimeException('Price مطلوب للمنتج بسعر معلن');
        }

        if ($existing) {
            $existing->fill($attributes);
            Product::validate($existing->getAttributes(), $existing);
            $existing->save();
            if ($this->provided($row, 'Colors') || $this->provided($row, 'Sizes')) {
                $this->addVariants($existing, $row);
            }

            return ['status' => 'updated'];
        }

        $slug = Str::slug($name) ?: $name;

        if (Product::query()->where('slug', $slug)->exists()) {
            throw new \RuntimeException("الاسم ينتج رابطًا مستخدمًا بالفعل: {$slug}");
        }

        $attributes = array_merge($attributes, [
            'product_code' => $code,
            'slug' => $slug,
            'price' => $price,
            'color_enabled' => $attributes['color_enabled'] ?? false,
            'size_enabled' => $attributes['size_enabled'] ?? false,
        ]);
        Product::validate($attributes);
        $product = Product::query()->create($attributes);
        $this->addVariants($product, $row);

        return ['status' => 'created'];
    }

    private function parseVisibility(array $row): PriceVisibility
    {
        $value = strtolower(trim((string) ($row['Price Visibility'] ?? '')));

        $visibility = PriceVisibility::tryFrom($value);

        if (! $visibility) {
            throw new \RuntimeException('Price Visibility يجب أن يكون public أو request_price');
        }

        return $visibility;
    }

    private function numericPrice(string $cell): string
    {
        if (! is_numeric($cell)) {
            throw new \RuntimeException('Price يجب أن يكون رقمًا');
        }

        if ((float) $cell < 0) {
            throw new \RuntimeException('Price يجب أن يكون صفرًا أو أكثر');
        }

        return number_format((float) $cell, 2, '.', '');
    }

    private function parseActive(array $row): bool
    {
        $value = strtolower(trim((string) ($row['Active'] ?? '')));

        if (in_array($value, ['1', 'true', 'yes'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no'], true)) {
            return false;
        }

        throw new \RuntimeException('Active يجب أن يكون 1/0/true/false/yes/no');
    }

    private function resolveCategory(array $row): Category
    {
        $name = trim((string) ($row['Category'] ?? ''));

        if (blank($name)) {
            throw new \RuntimeException('الحقل Category مطلوب');
        }

        $category = Category::query()->where('name', $name)->first();

        if ($category) {
            return $category;
        }

        $slug = Str::slug($name) ?: $name;

        if (Category::query()->where('slug', $slug)->where('name', '!=', $name)->exists()) {
            throw new \RuntimeException("تعذر إنشاء الفئة {$name} بسبب تعارض المُعرّف");
        }

        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'active' => true,
        ]);
    }

    private function provided(array $row, string $header): bool
    {
        return filled(trim((string) ($row[$header] ?? '')));
    }

    private function addVariants(Product $product, array $row): void
    {
        $colors = $this->options($row, 'Colors', VariantColor::activeNames());
        $sizes = $this->options($row, 'Sizes', VariantSize::activeNames());
        if ($colors === [] || $sizes === []) {
            if ($this->provided($row, 'Colors') || $this->provided($row, 'Sizes')) {
                throw new \RuntimeException('أضف الألوان والمقاسات النشطة في الإعدادات أو حددها في الملف.');
            }

            return;
        }
        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $candidate = ProductVariant::validate(['color' => $color, 'size' => $size, 'available_quantity' => 0], $product);
                if (! ProductVariant::existsFor($product, $color, $size)) {
                    $product->variants()->create($candidate);
                }
            }
        }
    }

    private function options(array $row, string $header, array $defaults): array
    {
        if (! $this->provided($row, $header)) {
            return $defaults;
        }
        $options = array_map('trim', preg_split('/[,،;|]/u', (string) $row[$header]));
        if (in_array('', $options, true) || count(array_unique($options)) !== count($options)) {
            throw new \RuntimeException("{$header}: قيم فارغة أو مكررة");
        }

        return $options;
    }

    private function reason(\Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = (string) (collect($exception->errors())->flatten()->first() ?? '');
        } else {
            $message = $exception->getMessage();
        }

        if ($message === '' || str_contains($message, 'SQLSTATE')) {
            $message = 'بيانات غير صالحة في الصف';
        }

        return $message;
    }
}
