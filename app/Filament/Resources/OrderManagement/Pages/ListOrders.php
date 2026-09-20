<?php

namespace App\Filament\Resources\OrderManagement\Pages;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Models\Order;
use App\Support\Exports\OrderItemsExport;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    use HasExcelExport;

    protected static string $resource = OrderManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->excelExportAction(
                'exportExcel',
                'تصدير النتائج Excel',
                fn (Builder $orders) => OrderItemsExport::forOrders($orders),
            ),
        ];
    }

    /**
     * Status-based operational tabs with trusted, single-query counts.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Order::statusCounts();

        $tabs = [];

        foreach (OrderStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->badge($counts[$status->value] ?? 0)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
