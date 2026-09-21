<?php

namespace App\Filament\Resources\OrderManagement\Schemas;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrderEditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.orders.sections.order'))
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label(__('filament.fields.admin_notes'))
                            ->rows(3)
                            ->maxLength(65535),
                        Placeholder::make('customer_notes_display')
                            ->label(__('filament.fields.customer_notes_readonly'))
                            ->content(fn (?Order $record): string => $record?->customer_notes ?: __('filament.common.none')),
                    ])
                    ->columns(2),
                Section::make(__('filament.orders.sections.items'))
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->minItems(1)
                            ->addActionLabel(__('filament.orders.add_item'))
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make(__('filament.fields.product'))
                                    ->width('32%')
                                    ->markAsRequired(),
                                TableColumn::make(__('filament.fields.color_size'))
                                    ->width('48%')
                                    ->markAsRequired(),
                                TableColumn::make(__('filament.fields.quantity_per_color'))
                                    ->width('15%')
                                    ->markAsRequired(),
                            ])
                            ->schema([
                                Hidden::make('id'),
                                Select::make('product_id')
                                    ->label(__('filament.fields.product'))
                                    ->options(function (?Order $record): array {
                                        $currentProductIds = $record?->items->pluck('product_id')->filter()->unique()->all() ?? [];

                                        return Product::query()
                                            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $currentProductIds))
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('product_variant_id', null)),
                                Select::make('product_variant_id')
                                    ->label(__('filament.fields.color_size'))
                                    ->options(function (Get $get): array {
                                        $productId = $get('product_id');

                                        if (blank($productId)) {
                                            return [];
                                        }

                                        return ProductVariant::query()
                                            ->where('product_id', (int) $productId)
                                            ->orderBy('color')
                                            ->orderBy('size')
                                            ->get()
                                            ->mapWithKeys(function (ProductVariant $variant): array {
                                                $dimensions = collect([$variant->color, $variant->size])
                                                    ->filter(fn ($value) => filled($value))
                                                    ->implode(' / ');

                                                return [
                                                    $variant->id => ($dimensions === '' ? '' : $dimensions.' — ')
                                                        .__('filament.orders.available', ['count' => $variant->available_quantity]),
                                                ];
                                            })
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required(),
                                TextInput::make('requested_quantity')
                                    ->label(__('filament.fields.quantity_per_color'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(OrderItem::MAX_QUANTITY)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
