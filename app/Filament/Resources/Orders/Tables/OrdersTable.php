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
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.phone')
                    ->label('الموبايل')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('total_quantity')
                    ->label('الكمية المطلوبة')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('items_sum_delivered_quantity')
                    ->label('تم تسليمه')
                    ->numeric()
                    ->default(0),
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
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
                        'تصدير المحدد Excel',
                        fn (EloquentCollection $records) => OrderItemsExport::forOrderIds($records->modelKeys(), 'delivered-orders'),
                    ),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('لا توجد طلبات مُسلَّمة بعد');
    }
}
