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
                            ->label(__('filament.fields.customer_code'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->hintIcon(Heroicon::InformationCircle)
                            ->hintIconTooltip(__('filament.customers.accounting_reference')),
                        TextInput::make('name')
                            ->label(__('filament.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_name')
                            ->label(__('filament.fields.company'))
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('filament.fields.phone'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('whatsapp')
                            ->label(__('filament.fields.whatsapp'))
                            ->maxLength(255),
                        TextInput::make('governorate')
                            ->label(__('filament.fields.governorate'))
                            ->maxLength(255),
                        TextInput::make('city')
                            ->label(__('filament.fields.city'))
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label(__('filament.fields.address'))
                            ->maxLength(255),
                    ]),
                Textarea::make('notes')
                    ->label(__('filament.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }
}
