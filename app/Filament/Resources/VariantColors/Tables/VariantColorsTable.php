<?php

namespace App\Filament\Resources\VariantColors\Tables;

use App\Filament\Concerns\HasExcelExport;
use App\Models\VariantColor;
use App\Support\Exports\VariantColorsExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class VariantColorsTable
{
    use HasExcelExport;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::excelExportSelectedAction(
                        'exportSelected',
                        'تصدير المحدد Excel',
                        fn (EloquentCollection $records) => new VariantColorsExport(VariantColor::query()->whereKey($records->modelKeys())),
                    ),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
