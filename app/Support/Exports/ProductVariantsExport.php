<?php

namespace App\Support\Exports;

use App\Models\ProductVariant;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ProductVariantsExport extends BusinessExport
{
    public function query(): Builder
    {
        return $this->query->with('product:id,product_code,name');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Product Code',
            'Product Name',
            'Color',
            'Size',
            'Available Quantity',
        ];
    }

    /**
     * @param  ProductVariant  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        return [
            $record->product?->product_code,
            $record->product?->name,
            $record->color,
            $record->size,
            $record->available_quantity,
        ];
    }

    public function title(): string
    {
        return 'Product Variants';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName('product-variants');
    }
}
