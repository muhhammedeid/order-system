<?php

namespace App\Support\Exports;

use App\Models\VariantColor;

class VariantColorsExport extends BusinessExport
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
     * @param  VariantColor  $record
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
        return 'Variant Colors';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName('variant-colors');
    }
}
