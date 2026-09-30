<?php

namespace App\Filament\Resources\OrderManagement;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderManagement\Pages\ConfirmOrder;
use App\Filament\Resources\OrderManagement\Pages\EditOrder;
use App\Filament\Resources\OrderManagement\Pages\ListOrders;
use App\Filament\Resources\OrderManagement\Pages\ViewOrder;
use App\Filament\Resources\OrderManagement\Schemas\OrderEditForm;
use App\Filament\Resources\OrderManagement\Tables\ManagedOrdersTable;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Primary operational order workspace: reviewing, editing, confirming,
 * cancelling and recording deliveries. The delivered history lives in the
 * read-only Orders resource.
 */
class OrderManagementResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = -11;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.order_management');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.operations');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.order_management');
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return OrderEditForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManagedOrdersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('customer:id,name,phone,whatsapp,company_name,governorate,city,address')
            ->withSum('items', 'delivered_quantity');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
            'confirm' => ConfirmOrder::route('/{record}/confirm'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Order && $record->isEditable();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) (Order::statusCounts()[OrderStatus::New->value] ?? 0);
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
}
