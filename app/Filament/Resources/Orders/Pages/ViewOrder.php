<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\OrderManagement\OrderPrintAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\Exports\OrderItemsExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderPrintAction::make(),
            Action::make('exportItems')
                ->label('تصدير الطلب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => OrderItemsExport::forOrder($this->getRecord())->download()),
        ];
    }
}
