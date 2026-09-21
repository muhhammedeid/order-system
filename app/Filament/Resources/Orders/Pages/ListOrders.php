<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\Exports\OrderItemsExport;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    use HasExcelExport;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelExportAction(
                'exportExcel',
                __('filament.common.export_results_excel'),
                fn (Builder $orders) => OrderItemsExport::forOrders($orders, 'delivered-orders'),
            ),
        ];
    }
}
