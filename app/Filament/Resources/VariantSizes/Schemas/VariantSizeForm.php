<?php

namespace App\Filament\Resources\VariantSizes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VariantSizeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('sort_order')
                    ->label(__('filament.fields.sort_order'))
                    ->required()
                    ->integer()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('active')
                    ->label(__('filament.fields.active'))
                    ->default(true)
                    ->required(),
            ]);
    }
}
