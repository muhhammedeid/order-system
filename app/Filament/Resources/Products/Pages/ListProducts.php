<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\HasExcelExport;
use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Products\ProductResource;
use App\Support\Exports\ProductsExport;
use App\Support\Imports\ImportRunner;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    use HasExcelExport;
    use HasImportAction;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('filament.products.add'))
                ->icon(Heroicon::OutlinedPlus),
            $this->importAction(
                'importProducts',
                __('filament.products.import'),
                fn (string $path) => ImportRunner::products($path),
            ),
            $this->excelExportAction(
                'exportExcel',
                __('filament.common.export_excel'),
                fn (Builder $query) => new ProductsExport($query),
            ),
        ];
    }
}
