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
                    ->label(__('filament.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label(__('filament.fields.company'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('filament.fields.phone'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_code')
                    ->label(__('filament.fields.customer_code'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('filament.fields.created_at'))
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
                        __('filament.common.export_selected_excel'),
                        fn (EloquentCollection $records) => new CustomersExport(Customer::query()->whereKey($records->modelKeys())),
                    ),
                    DeleteBulkAction::make()
                        ->before(function (EloquentCollection $records, DeleteBulkAction $action): void {
                            if (! $records->contains(fn (Customer $record): bool => $record->isReferencedByOrders())) {
                                return;
                            }

                            Notification::make()
                                ->title(__('filament.customers.bulk_delete_blocked_title'))
                                ->body(__('filament.customers.bulk_delete_blocked_body'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}
