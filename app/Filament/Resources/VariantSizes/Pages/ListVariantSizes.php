<?php

namespace App\Filament\Resources\VariantSizes\Pages;

use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\VariantSizes\VariantSizeResource;
use App\Support\Exports\VariantSizesExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ListVariantSizes extends ListRecords
{
    use HasExcelExport;

    protected static string $resource = VariantSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->excelExportAction(
                'exportExcel',
                'تصدير Excel',
                fn (Builder $query) => new VariantSizesExport($query),
            ),
        ];
    }
}
