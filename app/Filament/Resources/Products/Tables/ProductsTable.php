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
                    ->label('Product Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('price_visibility')
                    ->label('Price Visibility')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof PriceVisibility ? $state->label() : $state),
                TextColumn::make('price')
                    ->numeric(),
                IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::excelExportSelectedAction(
                        'exportSelected',
                        'تصدير المحدد Excel',
                        fn (EloquentCollection $records) => new ProductsExport(Product::query()->whereKey($records->modelKeys())),
                    ),
                    DeleteBulkAction::make()
                        ->before(function (EloquentCollection $records, DeleteBulkAction $action): void {
                            if (! $records->contains(fn (Product $record): bool => $record->isReferencedByActiveOrder())) {
                                return;
                            }

                            Notification::make()
                                ->title('تعذّر حذف المنتجات المحددة')
                                ->body('لا يمكن حذف بعض المنتجات المحددة لأن مقاساتها مرتبطة بطلبات نشطة. لم يتم حذف أي منتج؛ يمكنك تعطيل المنتجات بدلًا من حذفها.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}
