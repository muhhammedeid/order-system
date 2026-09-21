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

    protected static ?int $navigationSort = -10;

    #[Url]
    public ?int $product = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.production_requirements');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.operations');
    }

    public function getTitle(): string
    {
        return __('admin.navigation.production_requirements');
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
                    ->label(__('admin.production.order_number'))
                    ->searchable()
                    ->url(fn (OrderItem $record): string => OrderManagementResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('order.customer.name')
                    ->label(__('admin.production.customer'))
                    ->searchable(),
                TextColumn::make('order.customer.phone')
                    ->label(__('admin.production.phone'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('product_code')
                    ->label(__('admin.production.product_code'))
                    ->searchable(),
                TextColumn::make('product_name')
                    ->label(__('admin.production.product'))
                    ->searchable(),
                TextColumn::make('color')
                    ->label(__('admin.production.color'))
                    ->placeholder('—'),
                TextColumn::make('size')
                    ->label(__('admin.production.size'))
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label(__('admin.production.ordered_quantity'))
                    ->numeric(),
                TextColumn::make('delivered_quantity')
                    ->label(__('admin.production.delivered_quantity'))
                    ->numeric(),
                TextColumn::make('remaining_quantity')
                    ->label(__('admin.production.remaining_quantity'))
                    ->numeric()
                    ->state(fn (OrderItem $record): int => $record->remaining_quantity),
                TextColumn::make('order.status')
                    ->label(__('admin.production.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->label() : $state)
                    ->color(fn ($state) => match ($state) {
                        OrderStatus::Confirmed => 'info',
                        OrderStatus::PartiallyDelivered => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('order_items.created_at', 'desc')
            ->emptyStateHeading(__('admin.production.empty'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Text::make(fn (): string => __('admin.production.total_heading', ['count' => number_format($this->outstandingTotal())]))
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
                __('admin.production.export'),
                fn (Builder $items) => new OrderItemsExport($items, 'production-requirements'),
            ),
            Action::make('clearProductFilter')
                ->label(__('admin.production.show_all'))
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

        return __('admin.production.selected_product', ['product' => $name]);
    }
}
