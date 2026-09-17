<?php

namespace App\Filament\Resources\VariantColors;

use App\Filament\Resources\VariantColors\Pages\CreateVariantColor;
use App\Filament\Resources\VariantColors\Pages\EditVariantColor;
use App\Filament\Resources\VariantColors\Pages\ListVariantColors;
use App\Filament\Resources\VariantColors\Schemas\VariantColorForm;
use App\Filament\Resources\VariantColors\Tables\VariantColorsTable;
use App\Models\VariantColor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VariantColorResource extends Resource
{
    protected static ?string $model = VariantColor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return VariantColorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VariantColorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVariantColors::route('/'),
            'create' => CreateVariantColor::route('/create'),
            'edit' => EditVariantColor::route('/{record}/edit'),
        ];
    }
}
