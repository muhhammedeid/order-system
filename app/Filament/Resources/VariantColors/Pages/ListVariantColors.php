<?php

namespace App\Filament\Resources\VariantColors\Pages;

use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\VariantColors\VariantColorResource;
use App\Support\Exports\VariantColorsExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ListVariantColors extends ListRecords
{
    use HasExcelExport;

    protected static string $resource = VariantColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->excelExportAction(
                'exportExcel',
                __('filament.common.export_excel'),
                fn (Builder $query) => new VariantColorsExport($query),
            ),
        ];
    }
}
