<?php

namespace App\Support\Imports;

use App\Enums\PriceVisibility;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductsImporter
{
    public const HEADERS = [
        'Product Code',
        'Product Name',
        'Category',
        'Price',
        'Price Visibility',
        'Active',
    ];

    public function process(array $keyedRows): ImportResult
    {
        $result = new ImportResult();
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
        $code = trim((string) ($row['Product Code'] ?? ''));
        $name = $this->requireTrimmed($row, 'Product Name');

        if (blank($code)) {
            throw new \RuntimeException('الحقل Product Code مطلوب');
        }

        if (in_array($code, $seenCodes, true)) {
            return ['status' => 'invalid', 'reason' => "كود منتج مكرر داخل الملف: {$code}"];
        }

        $seenCodes[] = $code;

        $visibility = $this->parseVisibility($row);
        $active = $this->parseActive($row);
        $category = $this->resolveCategory($row);

        $priceCell = trim((string) ($row['Price'] ?? ''));
        $priceProvided = filled($priceCell);
        $price = null;

        if ($priceProvided) {
            $price = $this->numericPrice($priceCell);
        } elseif ($visibility === PriceVisibility::PublicPrice && ! Product::query()->where('product_code', $code)->exists()) {
            throw new \RuntimeException('Price مطلوب للمنتج بسعر معلن');
        }

        $existing = Product::query()->where('product_code', $code)->first();

        if ($existing) {
            $attributes = [
                'name' => $name,
                'category_id' => $category?->id,
                'price_visibility' => $visibility,
                'active' => $active,
            ];

            if ($priceProvided) {
                $attributes['price'] = $price;
            }

            $existing->fill($attributes);
            $existing->save();

            return ['status' => 'updated'];
        }

        $slug = Str::slug($name) ?: $name;

        if (Product::query()->where('slug', $slug)->exists()) {
            return ['status' => 'invalid', 'reason' => "الاسم ينتج رابطًا مستخدمًا بالفعل: {$slug}"];
        }

        Product::query()->create([
            'product_code' => $code,
            'name' => $name,
            'slug' => $slug,
            'category_id' => $category?->id,
            'price_visibility' => $visibility,
            'price' => $price,
            'active' => $active,
        ]);

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

    private function requireTrimmed(array $row, string $header): string
    {
        $value = trim((string) ($row[$header] ?? ''));

        if (blank($value)) {
            throw new \RuntimeException("الحقل {$header} مطلوب");
        }

        return $value;
    }

    private function reason(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if ($message === '' || str_contains($message, 'SQLSTATE')) {
            $message = 'بيانات غير صالحة في الصف';
        }

        return $message;
    }
}
