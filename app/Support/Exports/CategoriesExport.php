<?php

namespace App\Support\Exports;

use App\Models\Category;

class CategoriesExport extends BusinessExport
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Name',
            'Slug',
            'Active',
            'Created At',
        ];
    }

    /**
     * @param  Category  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        return [
            $record->name,
            $record->slug,
            $record->active ? 1 : 0,
            $this->localDateTime($record->created_at),
        ];
    }

    public function title(): string
    {
        return 'Categories';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName('categories');
    }
}
