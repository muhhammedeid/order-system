<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\WhatsAppMarketingStatus;
use App\Models\Customer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

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
                Section::make(__('admin.whatsapp.marketing.section'))
                    ->schema([
                        Select::make('whatsapp_marketing_status')
                            ->label(__('admin.whatsapp.marketing.status'))
                            ->options(collect(WhatsAppMarketingStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                            ->default(WhatsAppMarketingStatus::Unknown->value)
                            ->required()
                            ->rules([Rule::in(array_column(WhatsAppMarketingStatus::cases(), 'value'))])
                            ->helperText(__('admin.whatsapp.marketing.hint')),
                        Placeholder::make('whatsapp_marketing_opted_in_at_display')
                            ->label(__('admin.whatsapp.marketing.opted_in_at'))
                            ->content(fn (?Customer $record): string => $record?->whatsapp_marketing_opted_in_at?->format('Y-m-d H:i') ?? __('filament.common.none')),
                        Placeholder::make('whatsapp_marketing_opted_out_at_display')
                            ->label(__('admin.whatsapp.marketing.opted_out_at'))
                            ->content(fn (?Customer $record): string => $record?->whatsapp_marketing_opted_out_at?->format('Y-m-d H:i') ?? __('filament.common.none')),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
