<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\PriceVisibility;
use App\Filament\Concerns\HasExcelExport;
use App\Models\Product;
use App\Support\Exports\ProductsExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ProductsTable
{
    use HasExcelExport;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_code')
                    ->label(__('filament.fields.product_code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('filament.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('filament.fields.category'))
                    ->sortable(),
                TextColumn::make('price_visibility')
                    ->label(__('filament.fields.price_visibility'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof PriceVisibility ? $state->label() : $state),
                TextColumn::make('price')
                    ->label(__('filament.fields.price'))
                    ->numeric(),
                IconColumn::make('active')
                    ->label(__('filament.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('filament.fields.status'))
                    ->placeholder(__('filament.common.all'))
                    ->trueLabel(__('filament.common.active'))
                    ->falseLabel(__('filament.common.inactive')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::excelExportSelectedAction(
                        'exportSelected',
                        __('filament.common.export_selected_excel'),
                        fn (EloquentCollection $records) => new ProductsExport(Product::query()->whereKey($records->modelKeys())),
                    ),
                    DeleteBulkAction::make()
                        ->before(function (EloquentCollection $records, DeleteBulkAction $action): void {
                            if (! $records->contains(fn (Product $record): bool => $record->isReferencedByActiveOrder())) {
                                return;
                            }

                            Notification::make()
                                ->title(__('filament.products.bulk_delete_blocked_title'))
                                ->body(__('filament.products.bulk_delete_blocked_body'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}
