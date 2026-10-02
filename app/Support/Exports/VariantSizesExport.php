<?php

namespace App\Support\Exports;

use App\Models\VariantSize;

class VariantSizesExport extends BusinessExport
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Name',
            'Sort Order',
            'Active',
        ];
    }

    /**
     * @param  VariantSize  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        return [
            $record->name,
            $record->sort_order,
            $record->active ? 1 : 0,
        ];
    }

    public function title(): string
    {
        return 'Variant Sizes';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName('variant-sizes');
    }
}
