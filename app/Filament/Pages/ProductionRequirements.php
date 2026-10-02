<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasExcelExport;
use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Filament\Widgets\ProductionRequirementsWidget;
use App\Models\OrderItemColorQuantity;
use App\Models\Product;
use App\Support\Exports\ProductionRequirementsExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
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
 * Transparent source view for the current production requirements: one row
 * per ordered color of confirmed / partially delivered orders that still
 * has a positive remaining quantity. Colors that were fully delivered never
 * appear, and every quantity belongs to a single snapshot color, so no row
 * mixes colors with different delivered or remaining quantities.
 *
 * The color cards appear first, directly under the page title and actions,
 * and follow the same product filter.
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
            ->query(OrderItemColorQuantity::outstandingForProduction($this->product))
            ->columns([
                TextColumn::make('orderItem.order.order_number')
                    ->label(__('admin.production.order_number'))
                    ->searchable()
                    ->url(fn (OrderItemColorQuantity $record): string => OrderManagementResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('orderItem.order.customer.name')
                    ->label(__('admin.production.customer'))
                    ->searchable(),
                TextColumn::make('orderItem.order.customer.phone')
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
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('order_item_color_quantities.color', 'like', "%{$search}%")),
                TextColumn::make('size')
                    ->label(__('admin.production.size'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('requested_quantity')
                    ->label(__('admin.production.requested_per_color'))
                    ->numeric(),
                TextColumn::make('delivered_quantity')
                    ->label(__('admin.production.delivered_per_color'))
                    ->numeric(),
                TextColumn::make('remaining_quantity')
                    ->label(__('admin.production.remaining_per_color'))
                    ->numeric(),
                TextColumn::make('orderItem.order.status')
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
                Livewire::make(ProductionRequirementsWidget::class, [
                    'product' => $this->product,
                ])->key('production-requirements-'.($this->product ?? 'all')),
                Section::make()
                    ->schema([
                        Text::make(fn (): string => __('admin.production.pending_colors_heading', ['count' => number_format($this->pendingColorsCount())]))
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
                fn (Builder $items) => new ProductionRequirementsExport($items),
            ),
            Action::make('clearProductFilter')
                ->label(__('admin.production.show_all'))
                ->color('gray')
                ->url(static::getUrl())
                ->visible(fn (): bool => filled($this->product)),
        ];
    }

    /**
     * Number of ordered colors that still require production, restricted to
     * the optional product filter. Different color requirements are never
     * summed into one manufactured quantity.
     */
    public function pendingColorsCount(): int
    {
        return OrderItemColorQuantity::outstandingColorCount($this->product);
    }

    private function selectedProductLabel(): string
    {
        $name = Product::query()->whereKey($this->product)->value('name') ?? "#{$this->product}";

        return __('admin.production.selected_product', ['product' => $name]);
    }
}
