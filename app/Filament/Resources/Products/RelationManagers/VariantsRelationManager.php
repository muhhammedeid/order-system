<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductVariant;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants';

    public static function colorOptions(?ProductVariant $record): array
    {
        return self::options(VariantColor::query(), $record?->color);
    }

    public static function sizeOptions(?ProductVariant $record): array
    {
        return self::options(VariantSize::query(), $record?->size);
    }

    protected static function options($query, ?string $currentValue): array
    {
        $options = $query
            ->where('active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();

        if (filled($currentValue) && !array_key_exists($currentValue, $options)) {
            $options[$currentValue] = $currentValue;
        }

        return $options;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('color')
                    ->required()
                    ->searchable()
                    ->options(fn (?ProductVariant $record) => self::colorOptions($record)),
                Select::make('size')
                    ->required()
                    ->searchable()
                    ->options(fn (?ProductVariant $record) => self::sizeOptions($record)),
                TextInput::make('available_quantity')
                    ->label('Available Quantity')
                    ->required()
                    ->integer()
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('color')
            ->columns([
                TextColumn::make('color')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('available_quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
