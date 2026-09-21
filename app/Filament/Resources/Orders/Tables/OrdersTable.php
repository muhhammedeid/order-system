<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Concerns\HasExcelExport;
use App\Support\Exports\OrderItemsExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class OrdersTable
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
                TextColumn::make('total_quantity')
                    ->label(__('filament.fields.ordered_quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('items_sum_delivered_quantity')
                    ->label(__('filament.fields.delivered_quantity'))
                    ->numeric()
                    ->default(0),
                TextColumn::make('created_at')
                    ->label(__('filament.fields.order_date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('filament.fields.updated_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::excelExportSelectedAction(
                        'exportSelected',
                        __('filament.common.export_selected_excel'),
                        fn (EloquentCollection $records) => OrderItemsExport::forOrderIds($records->modelKeys(), 'delivered-orders'),
                    ),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading(__('filament.orders.delivered_empty'));
    }
}
