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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 91;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.colors');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.color');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.models.colors');
    }

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
