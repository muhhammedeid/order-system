<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(self::sections());
    }

    public static function editForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('admin_notes')
                    ->label('Admin Notes')
                    ->maxLength(65535)
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    protected static function sections(): array
    {
        return [
            Section::make('بيانات الطلب')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('order_number')
                                ->label('رقم الطلب')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('status')
                                ->label('الحالة')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : $state),
                            TextEntry::make('created_at')
                                ->label('تاريخ الطلب')
                                ->dateTime('Y-m-d H:i'),
                            TextEntry::make('total_quantity')
                                ->label('إجمالي القطع')
                                ->numeric(),
                            TextEntry::make('customer_notes')
                                ->label('ملاحظات العميل')
                                ->placeholder('—')
                                ->columnSpan(2),
                        ]),
                ]),
            Section::make('بنود الطلب')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            Grid::make(4)
                                ->schema([
                                    TextEntry::make('product_code')
                                        ->label('كود المنتج'),
                                    TextEntry::make('product_name')
                                        ->label('اسم المنتج'),
                                    TextEntry::make('color')
                                        ->label('اللون'),
                                    TextEntry::make('size')
                                        ->label('المقاس'),
                                    TextEntry::make('quantity')
                                        ->label('الكمية')
                                        ->numeric(),
                                    TextEntry::make('price_visibility')
                                        ->label('نوع السعر')
                                        ->formatStateUsing(fn ($state) => $state === 'public' ? 'سعر معلن' : 'السعر عند الطلب'),
                                    TextEntry::make('unit_price')
                                        ->label('سعر الوحدة')
                                        ->numeric()
                                        ->formatStateUsing(fn ($state) => $state === null ? 'السعر عند الطلب' : (string) $state),
                                    TextEntry::make('unit_price')
                                        ->label('إجمالي البند')
                                        ->numeric()
                                        ->formatStateUsing(function ($state, $record) {
                                            return $state === null
                                                ? 'السعر عند الطلب'
                                                : (string) bcmul((string) $state, (string) $record->quantity, 2);
                                        }),
                                ])
                                ->columns(4),
                        ]),
                ]),
            Section::make('بيانات العميل')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('customer.name')
                                ->label('اسم العميل'),
                            TextEntry::make('customer.phone')
                                ->label('رقم الموبايل'),
                            TextEntry::make('customer.whatsapp')
                                ->label('واتساب')
                                ->placeholder('—'),
                            TextEntry::make('customer.company_name')
                                ->label('الشركة / المحل')
                                ->placeholder('—'),
                            TextEntry::make('customer.governorate')
                                ->label('المحافظة')
                                ->placeholder('—'),
                            TextEntry::make('customer.city')
                                ->label('المدينة')
                                ->placeholder('—'),
                            TextEntry::make('customer.address')
                                ->label('العنوان')
                                ->placeholder('—'),
                        ]),
                ]),
        ];
    }
}
