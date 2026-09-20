<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Concerns\HasExcelExport;
use App\Models\Customer;
use App\Support\Exports\CustomersExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CustomersTable
{
    use HasExcelExport;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_code')
                    ->label('Customer Code')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::excelExportSelectedAction(
                        'exportSelected',
                        'تصدير المحدد Excel',
                        fn (EloquentCollection $records) => new CustomersExport(Customer::query()->whereKey($records->modelKeys())),
                    ),
                    DeleteBulkAction::make()
                        ->before(function (EloquentCollection $records, DeleteBulkAction $action): void {
                            if (! $records->contains(fn (Customer $record): bool => $record->isReferencedByOrders())) {
                                return;
                            }

                            Notification::make()
                                ->title('تعذّر حذف العملاء المحددين')
                                ->body('لا يمكن حذف بعض العملاء المحددين لأنهم مرتبطون بطلبات مسجلة في النظام. لم يتم حذف أي عميل؛ يمكنك الإبقاء على بياناتهم للحفاظ على سجل الطلبات.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}
