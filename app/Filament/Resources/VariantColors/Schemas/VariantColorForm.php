<?php

namespace App\Filament\Resources\VariantColors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VariantColorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('sort_order')
                    ->required()
                    ->integer()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
