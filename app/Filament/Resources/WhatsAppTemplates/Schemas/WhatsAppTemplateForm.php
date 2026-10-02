<?php

namespace App\Filament\Resources\WhatsAppTemplates\Schemas;

use App\Enums\WhatsAppTemplateType;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\Templates\WhatsAppTemplateVariables;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class WhatsAppTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label(__('admin.whatsapp.templates.fields.key'))
                    ->helperText(__('admin.whatsapp.templates.key_hint'))
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (?WhatsAppTemplate $record): bool => $record?->key !== null)
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label(__('admin.whatsapp.templates.fields.name'))
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true),
                Select::make('type')
                    ->label(__('admin.whatsapp.templates.fields.type'))
                    ->options(collect(WhatsAppTemplateType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(WhatsAppTemplateType::Marketing->value)
                    ->required()
                    ->disabled(fn (?WhatsAppTemplate $record): bool => $record?->key !== null)
                    ->rules([Rule::in(array_column(WhatsAppTemplateType::cases(), 'value'))]),
                Textarea::make('body')
                    ->label(__('admin.whatsapp.templates.fields.body'))
                    ->required()
                    ->rows(6)
                    ->maxLength(4096)
                    ->columnSpanFull()
                    ->helperText(__('admin.whatsapp.templates.variables_hint', [
                        'variables' => collect(WhatsAppTemplateVariables::allowed())
                            ->map(fn (string $variable): string => '{{'.$variable.'}}')
                            ->implode(', '),
                    ]))
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $type = WhatsAppTemplateType::tryFrom((string) $get('type'));
                        $allowed = in_array($type, [WhatsAppTemplateType::Order, WhatsAppTemplateType::OrderUpdate], true)
                            ? ($get('key') === 'order_placed_owner' ? WhatsAppTemplateVariables::ownerContext() : WhatsAppTemplateVariables::orderContext())
                            : WhatsAppTemplateVariables::customerContext();

                        $unknown = WhatsAppTemplateVariables::unknownTokens((string) $value, $allowed);

                        if ($unknown !== []) {
                            $fail(__('admin.whatsapp.templates.errors.unknown_tokens', [
                                'tokens' => implode(', ', $unknown),
                            ]));
                        }
                    }),
                Toggle::make('active')
                    ->label(__('admin.whatsapp.templates.fields.active'))
                    ->default(true)
                    ->required(),
            ]);
    }
}
