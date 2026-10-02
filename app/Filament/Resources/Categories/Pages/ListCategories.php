<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\Categories\CategoryResource;
use App\Support\Exports\CategoriesExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ListCategories extends ListRecords
{
    use HasExcelExport;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->excelExportAction(
                'exportExcel',
                __('filament.common.export_excel'),
                fn (Builder $query) => new CategoriesExport($query),
            ),
        ];
    }
}
