<?php

namespace App\Support\Exports;

use App\Enums\PriceVisibility;
use App\Models\Product;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

/**
 * Product grain export using the same headers as the product import
 * contract, so exported files can be reviewed and re-imported safely.
 * Price is blank for request_price products (operational export only).
 */
class ProductsExport extends BusinessExport implements WithColumnFormatting
{
    public function query(): Builder
    {
        return $this->query->with('category:id,name');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Product Code',
            'Product Name',
            'Category',
            'Price',
            'Price Visibility',
            'Active',
        ];
    }

    /**
     * @param  Product  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        $isPublicPrice = $record->price_visibility === PriceVisibility::PublicPrice;

        return [
            $record->product_code,
            $record->name,
            $record->category?->name,
            $isPublicPrice && $record->price !== null ? (float) $record->price : null,
            $record->price_visibility->value,
            $record->active ? 1 : 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'D' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Products';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName('products');
    }
}
