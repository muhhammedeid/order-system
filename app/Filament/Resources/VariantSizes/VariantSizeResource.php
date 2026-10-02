<?php

namespace App\Filament\Resources\VariantSizes;

use App\Filament\Resources\VariantSizes\Pages\CreateVariantSize;
use App\Filament\Resources\VariantSizes\Pages\EditVariantSize;
use App\Filament\Resources\VariantSizes\Pages\ListVariantSizes;
use App\Filament\Resources\VariantSizes\Schemas\VariantSizeForm;
use App\Filament\Resources\VariantSizes\Tables\VariantSizesTable;
use App\Models\VariantSize;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VariantSizeResource extends Resource
{
    protected static ?string $model = VariantSize::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static ?int $navigationSort = 92;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.sizes');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.size');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.models.sizes');
    }

    public static function form(Schema $schema): Schema
    {
        return VariantSizeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VariantSizesTable::configure($table);
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
            'index' => ListVariantSizes::route('/'),
            'create' => CreateVariantSize::route('/create'),
            'edit' => EditVariantSize::route('/{record}/edit'),
        ];
    }
}
