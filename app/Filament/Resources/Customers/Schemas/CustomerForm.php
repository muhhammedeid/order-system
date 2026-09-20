<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('customer_code')
                            ->label('Customer Code')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->hintIcon(Heroicon::InformationCircle)
                            ->hintIconTooltip('Reference from the accounting system'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_name')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('whatsapp')
                            ->maxLength(255),
                        TextInput::make('governorate')
                            ->maxLength(255),
                        TextInput::make('city')
                            ->maxLength(255),
                        TextInput::make('address')
                            ->maxLength(255),
                    ]),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
