<?php

namespace App\Filament\Resources\OrderManagement\Tables;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\OrderManagement\OrderStatusActions;
use App\Models\Order;
use App\Support\Exports\OrderItemsExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ManagedOrdersTable
{
    use HasExcelExport;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('filament.fields.order_number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('filament.fields.customer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.phone')
                    ->label(__('filament.fields.phone'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('filament.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : $state),
                TextColumn::make('total_quantity')
                    ->label(__('filament.fields.ordered_quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('items_sum_delivered_quantity')
                    ->label(__('filament.fields.delivered_quantity'))
                    ->numeric()
                    ->default(0),
                TextColumn::make('remaining_quantity')
                    ->label(__('filament.fields.remaining_quantity'))
                    ->numeric()
                    ->state(fn (Order $record): int => max(0, (int) $record->total_quantity - (int) ($record->items_sum_delivered_quantity ?? 0))),
                TextColumn::make('created_at')
                    ->label(__('filament.fields.order_date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                OrderStatusActions::edit(),
                OrderStatusActions::review(),
                OrderStatusActions::confirm(),
                OrderStatusActions::cancel(),
                OrderStatusActions::recordDelivery(),
                OrderStatusActions::deliverAll(),
                OrderStatusActions::reconcileDeliveries(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::excelExportSelectedAction(
                        'exportSelected',
                        __('filament.common.export_selected_excel'),
                        fn (EloquentCollection $records) => OrderItemsExport::forOrderIds($records->modelKeys()),
                    ),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('filament.orders.status_empty'));
    }
}
