<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Pages\ProductionRequirements;
use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Operational KPI cards. Every number links to the source view using the
 * same criteria, so the displayed value can always be reconciled.
 */
class OrderStatsWidget extends StatsOverviewWidget
{
    /**
     * Business-local day for "today's orders". Stored timestamps remain
     * UTC; only this card's day boundary is computed in Cairo time.
     */
    private const BUSINESS_TIMEZONE = 'Africa/Cairo';

    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $statusCounts = Order::statusCounts();

        return [
            Stat::make(__('admin.stats.new_orders'), $statusCounts[OrderStatus::New->value] ?? 0)
                ->description(__('admin.stats.new_orders_description'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning')
                ->url(OrderManagementResource::getUrl('index', ['tab' => OrderStatus::New->value])),
            Stat::make(__('admin.stats.today_orders'), $this->todayOrdersCount())
                ->description(__('admin.stats.today_orders_description'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url(OrderManagementResource::getUrl('index')),
            Stat::make(__('admin.stats.confirmed_orders'), Order::confirmedInExecutionCount())
                ->description(__('admin.stats.confirmed_orders_description'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url(OrderManagementResource::getUrl('index', ['tab' => OrderStatus::Confirmed->value])),
            Stat::make(__('admin.stats.partial_delivery'), $statusCounts[OrderStatus::PartiallyDelivered->value] ?? 0)
                ->description(__('admin.stats.partial_delivery_description'))
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning')
                ->url(OrderManagementResource::getUrl('index', ['tab' => OrderStatus::PartiallyDelivered->value])),
            Stat::make(__('admin.stats.delivered_orders'), $statusCounts[OrderStatus::Delivered->value] ?? 0)
                ->description(__('admin.stats.delivered_orders_description'))
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray')
                ->url(OrderResource::getUrl('index')),
            Stat::make(__('admin.stats.production_required'), OrderItem::productionRemainingQuantityTotal())
                ->description(__('admin.stats.production_required_description'))
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('danger')
                ->url(ProductionRequirements::getUrl()),
            Stat::make(__('admin.stats.active_products'), Product::query()->where('active', true)->count())
                ->description(__('admin.stats.active_products_description'))
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info')
                ->url('/admin/products?'.http_build_query(['filters' => ['active' => ['value' => 'true']]])),
            Stat::make(__('admin.stats.customers'), Customer::query()->count())
                ->description(__('admin.stats.customers_description'))
                ->descriptionIcon('heroicon-m-users')
                ->color('gray')
                ->url('/admin/customers'),
        ];
    }

    private function todayOrdersCount(): int
    {
        $startOfBusinessDay = now(self::BUSINESS_TIMEZONE)->startOfDay()->utc();

        return Order::query()->where('created_at', '>=', $startOfBusinessDay)->count();
    }
}
