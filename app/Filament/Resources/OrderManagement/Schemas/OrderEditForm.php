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
                Section::make('بيانات الطلب')
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('ملاحظات الإدارة')
                            ->rows(3)
                            ->maxLength(65535),
                        Placeholder::make('customer_notes_display')
                            ->label('ملاحظات العميل (للقراءة فقط)')
                            ->content(fn (?Order $record): string => $record?->customer_notes ?: '—'),
                    ])
                    ->columns(2),
                Section::make('بنود الطلب')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->minItems(1)
                            ->addActionLabel('إضافة صنف')
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('المنتج')
                                    ->width('32%')
                                    ->markAsRequired(),
                                TableColumn::make('اللون / المقاس')
                                    ->width('48%')
                                    ->markAsRequired(),
                                TableColumn::make('الكمية')
                                    ->width('15%')
                                    ->markAsRequired(),
                            ])
                            ->schema([
                                Hidden::make('id'),
                                Select::make('product_id')
                                    ->label('المنتج')
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
                                    ->label('اللون / المقاس')
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
                                            ->mapWithKeys(fn (ProductVariant $variant): array => [
                                                $variant->id => $variant->color
                                                    .($variant->size ? ' / '.$variant->size : '')
                                                    .' — المتاح: '.$variant->available_quantity,
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('الكمية')
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
