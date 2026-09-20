<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Concerns\HasExcelExport;
use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Support\Exports\CustomersExport;
use App\Support\Imports\ImportRunner;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    use HasExcelExport;
    use HasImportAction;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(
                'importCustomers',
                'استيراد العملاء',
                fn (string $path) => ImportRunner::customers($path),
            ),
            $this->excelExportAction(
                'exportExcel',
                'تصدير Excel',
                fn (Builder $query) => new CustomersExport($query),
            ),
        ];
    }
}
