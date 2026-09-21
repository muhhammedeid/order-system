<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\PriceVisibility;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product_code')
                    ->label(__('filament.fields.product_code'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('category_id')
                    ->label(__('filament.fields.category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->label(__('filament.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('filament.fields.slug'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label(__('filament.fields.description'))
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Select::make('price_visibility')
                    ->label(__('filament.fields.price_visibility'))
                    ->options(collect(PriceVisibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(PriceVisibility::PublicPrice->value)
                    ->required()
                    ->live(),
                TextInput::make('price')
                    ->label(__('filament.fields.price'))
                    ->numeric()
                    ->minValue(0)
                    ->rules(Product::priceRules())
                    ->nullable()
                    ->visible(fn ($get) => $get('price_visibility') !== PriceVisibility::RequestPrice->value),
                Toggle::make('active')
                    ->label(__('filament.fields.active'))
                    ->default(true)
                    ->required(),
                Toggle::make('color_enabled')
                    ->label(__('filament.products.color_enabled'))
                    ->helperText(__('filament.products.color_enabled_help'))
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(fn ($state, $set) => ! $state ? $set('size_enabled', false) : null),
                Toggle::make('size_enabled')
                    ->label(__('filament.products.size_enabled'))
                    ->helperText(__('filament.products.size_enabled_help'))
                    ->disabled(fn ($get): bool => ! (bool) $get('color_enabled'))
                    ->dehydrated()
                    ->default(false),
                Repeater::make('images')
                    ->relationship('images')
                    ->reorderable()
                    ->orderable('sort_order')
                    ->label(__('filament.fields.images'))
                    ->schema([
                        FileUpload::make('image_path')
                            ->label(__('filament.fields.image'))
                            ->image()
                            ->acceptedFileTypes(['image/*'])
                            ->disk(config('filesystems.product_images_disk'))
                            ->directory('products/images')
                            ->required(),
                        Hidden::make('sort_order')
                            ->default(0),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
