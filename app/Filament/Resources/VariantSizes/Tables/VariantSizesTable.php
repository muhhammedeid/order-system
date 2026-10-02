<?php

namespace App\Filament\Resources\VariantSizes\Tables;

use App\Filament\Concerns\HasExcelExport;
use App\Models\VariantSize;
use App\Support\Exports\VariantSizesExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class VariantSizesTable
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
                TextColumn::make('sort_order')
                    ->label(__('filament.fields.sort_order'))
                    ->sortable(),
                IconColumn::make('active')
                    ->label(__('filament.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::excelExportSelectedAction(
                        'exportSelected',
                        __('filament.common.export_selected_excel'),
                        fn (EloquentCollection $records) => new VariantSizesExport(VariantSize::query()->whereKey($records->modelKeys())),
                    ),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
