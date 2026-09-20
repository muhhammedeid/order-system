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
                    ->label('Product Code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Select::make('price_visibility')
                    ->label('Price Visibility')
                    ->options(collect(PriceVisibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(PriceVisibility::PublicPrice->value)
                    ->required()
                    ->live(),
                TextInput::make('price')
                    ->numeric()
                    ->minValue(0)
                    ->rules(Product::priceRules())
                    ->nullable()
                    ->visible(fn ($get) => $get('price_visibility') !== PriceVisibility::RequestPrice->value),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
                Toggle::make('size_enabled')
                    ->label('تفعيل اختيار المقاس')
                    ->helperText('عند تعطيله يختار العميل اللون والكمية فقط. لا يمكن تعطيله إذا كان المنتج يحتوي على مقاسات مسجلة.')
                    ->default(false),
                Repeater::make('images')
                    ->relationship('images')
                    ->reorderable()
                    ->orderable('sort_order')
                    ->label('Images')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Image')
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
