<?php

namespace App\Filament\Resources\OrderManagement;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

final class OrderPrintAction
{
    public static function make(): Action
    {
        return Action::make('printInvoice')
            ->label('طباعة الفاتورة')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (Order $record): string => route('filament.admin.orders.print', ['order' => $record]))
            ->openUrlInNewTab();
    }
}
