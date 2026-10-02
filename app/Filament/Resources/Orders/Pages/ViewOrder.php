<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\OrderManagement\OrderPrintAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Livewire\OrderWhatsAppPanel;
use App\Support\Exports\OrderItemsExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getInfolistContentComponent(),
            Livewire::make(OrderWhatsAppPanel::class, ['order' => $this->getRecord()->getKey()])
                ->key('order-whatsapp-'.$this->getRecord()->getKey()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderPrintAction::make(),
            Action::make('exportItems')
                ->label(__('filament.orders.export_order'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => OrderItemsExport::forOrder($this->getRecord())->download()),
        ];
    }
}
