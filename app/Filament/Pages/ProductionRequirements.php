<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Exports\OrderItemsExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Transparent source view for the outstanding production quantity KPI:
 * one row per ordered variant of confirmed / partially delivered orders
 * with a positive remaining quantity. Optionally filtered by product for
 * the dashboard catalog drill-down.
 */
class ProductionRequirements extends Page implements HasTable
{
    use HasExcelExport;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $title = 'المطلوب للتشغيل';

    protected static ?int $navigationSort = -10;

    #[Url]
    public ?int $product = null;

    public static function getNavigationLabel(): string
    {
        return 'المطلوب للتشغيل';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->inProduction()
                    ->withOutstandingQuantity()
                    ->when($this->product, fn (Builder $query, int $productId) => $query->where('order_items.product_id', $productId))
                    ->with([
                        'order' => fn ($query) => $query
                            ->select('id', 'order_number', 'status', 'customer_id')
                            ->with('customer:id,name,phone'),
                    ]),
            )
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->url(fn (OrderItem $record): string => OrderManagementResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('order.customer.name')
                    ->label('العميل')
                    ->searchable(),
                TextColumn::make('order.customer.phone')
                    ->label('الموبايل')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('product_code')
                    ->label('كود المنتج')
                    ->searchable(),
                TextColumn::make('product_name')
                    ->label('المنتج')
                    ->searchable(),
                TextColumn::make('color')
                    ->label('اللون'),
                TextColumn::make('size')
                    ->label('المقاس')
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label('الكمية المطلوبة')
                    ->numeric(),
                TextColumn::make('delivered_quantity')
                    ->label('تم تسليمه')
                    ->numeric(),
                TextColumn::make('remaining_quantity')
                    ->label('المتبقي')
                    ->numeric()
                    ->state(fn (OrderItem $record): int => $record->remaining_quantity),
                TextColumn::make('order.status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : $state)
                    ->color(fn ($state) => match ($state) {
                        OrderStatus::Confirmed => 'info',
                        OrderStatus::PartiallyDelivered => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('order_items.created_at', 'desc')
            ->emptyStateHeading('لا توجد كميات مطلوبة للتشغيل حالياً');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Text::make(fn (): string => 'إجمالي الكمية المطلوبة للتشغيل: '.$this->outstandingTotal())
                            ->weight(FontWeight::Bold),
                        Text::make(fn (): string => $this->selectedProductLabel())
                            ->visible(fn (): bool => filled($this->product)),
                    ]),
                EmbeddedTable::make(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->excelExportAction(
                'exportExcel',
                'تصدير النتائج Excel',
                fn (Builder $items) => new OrderItemsExport($items, 'production-requirements'),
            ),
            Action::make('clearProductFilter')
                ->label('عرض كل المنتجات')
                ->color('gray')
                ->url(static::getUrl())
                ->visible(fn (): bool => filled($this->product)),
        ];
    }

    /**
     * Same aggregate criterion as the Outstanding Quantity KPI, restricted
     * to the optional product filter so the header always reconciles with
     * the displayed rows.
     */
    public function outstandingTotal(): int
    {
        return OrderItem::outstandingQuantityTotal($this->product);
    }

    private function selectedProductLabel(): string
    {
        $name = Product::query()->whereKey($this->product)->value('name') ?? "#{$this->product}";

        return "المنتج المحدد: {$name}";
    }
}
