<?php

namespace App\Filament\Resources\OrderManagement\Pages;

use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Filament\Resources\OrderManagement\OrderPrintAction;
use App\Filament\Resources\OrderManagement\OrderStatusActions;
use App\Support\Exports\OrderItemsExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderStatusActions::review(),
            OrderStatusActions::edit(),
            OrderStatusActions::confirm(),
            OrderStatusActions::cancel(),
            OrderStatusActions::recordDelivery(),
            OrderStatusActions::deliverAll(),
            OrderPrintAction::make(),
            Action::make('exportItems')
                ->label(__('filament.orders.export_order'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => OrderItemsExport::forOrder($this->getRecord())->download()),
        ];
    }
}
