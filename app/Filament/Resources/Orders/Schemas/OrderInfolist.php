<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::orderSection(),
                self::itemsSection(),
                self::customerSection(),
            ]);
    }

    public static function configureReview(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 12])
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                self::orderSection(),
                                self::customerSection(),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 5]),
                        self::itemsSection()
                            ->columnSpan(['default' => 1, 'lg' => 7]),
                    ]),
            ]);
    }

    public static function orderSection(): Section
    {
        return Section::make(__('filament.orders.sections.order'))
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label(__('filament.fields.order_number'))
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label(__('filament.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : $state),
                        TextEntry::make('created_at')
                            ->label(__('filament.fields.order_date'))
                            ->dateTime('Y-m-d H:i'),
                        TextEntry::make('total_quantity')
                            ->label(__('filament.fields.total_pieces'))
                            ->numeric(),
                        TextEntry::make('customer_notes')
                            ->label(__('filament.fields.customer_notes'))
                            ->placeholder(__('filament.common.none'))
                            ->columnSpan(2),
                        TextEntry::make('admin_notes')
                            ->label(__('filament.fields.admin_notes'))
                            ->placeholder(__('filament.common.none'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function itemsSection(): Section
    {
        return Section::make(__('filament.orders.sections.items'))
            ->schema([
                RepeatableEntry::make('items')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('product_code')
                                    ->label(__('filament.fields.product_code')),
                                TextEntry::make('product_name')
                                    ->label(__('filament.fields.product')),
                                TextEntry::make('color')
                                    ->label(__('filament.fields.color'))
                                    ->placeholder(__('filament.common.none')),
                                TextEntry::make('size')
                                    ->label(__('filament.fields.size'))
                                    ->placeholder(__('filament.common.none')),
                                TextEntry::make('requested_quantity')
                                    ->label(__('filament.fields.quantity_per_color'))
                                    ->numeric(),
                                TextEntry::make('color_count')
                                    ->label(__('filament.fields.color_count'))
                                    ->numeric(),
                                TextEntry::make('quantity')
                                    ->label(__('filament.fields.total_pieces'))
                                    ->numeric(),
                                TextEntry::make('delivered_quantity')
                                    ->label(__('filament.fields.delivered_quantity'))
                                    ->numeric(),
                                TextEntry::make('remaining_quantity')
                                    ->label(__('filament.fields.remaining_quantity'))
                                    ->numeric(),
                                TextEntry::make('unallocated_delivered_quantity')
                                    ->label(__('filament.orders.unallocated_delivered'))
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn (OrderItem $record): bool => $record->hasUnallocatedDeliveries()),
                                TextEntry::make('price_visibility')
                                    ->label(__('filament.fields.price_type'))
                                    ->formatStateUsing(fn ($state) => $state === 'public'
                                        ? __('filament.orders.public_price')
                                        : __('filament.orders.request_price')),
                                TextEntry::make('unit_price')
                                    ->label(__('filament.fields.unit_price'))
                                    ->numeric()
                                    ->formatStateUsing(fn ($state) => $state === null ? __('filament.orders.request_price') : (string) $state),
                                TextEntry::make('line_total')
                                    ->label(__('filament.fields.line_total'))
                                    ->state(fn (OrderItem $record): string => $record->unit_price === null
                                        ? __('filament.orders.request_price')
                                        : bcmul((string) $record->unit_price, (string) $record->quantity, 2)),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    public static function customerSection(): Section
    {
        return Section::make(__('filament.orders.sections.customer'))
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label(__('filament.fields.customer_name')),
                        TextEntry::make('customer.phone')
                            ->label(__('filament.fields.mobile_number')),
                        TextEntry::make('customer.whatsapp')
                            ->label(__('filament.fields.whatsapp'))
                            ->placeholder(__('filament.common.none')),
                        TextEntry::make('customer.company_name')
                            ->label(__('filament.fields.company_store'))
                            ->placeholder(__('filament.common.none')),
                        TextEntry::make('customer.governorate')
                            ->label(__('filament.fields.governorate'))
                            ->placeholder(__('filament.common.none')),
                        TextEntry::make('customer.city')
                            ->label(__('filament.fields.city'))
                            ->placeholder(__('filament.common.none')),
                        TextEntry::make('customer.address')
                            ->label(__('filament.fields.address'))
                            ->placeholder(__('filament.common.none')),
                    ]),
            ]);
    }
}
