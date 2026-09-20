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
            Stat::make('طلبات جديدة', $statusCounts[OrderStatus::New->value] ?? 0)
                ->description('في انتظار المراجعة')
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning')
                ->url(OrderManagementResource::getUrl('index', ['tab' => OrderStatus::New->value])),
            Stat::make('طلبات اليوم', $this->todayOrdersCount())
                ->description('تم استلامها اليوم — بتوقيت القاهرة')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url(OrderManagementResource::getUrl('index')),
            Stat::make('طلبات مؤكدة', Order::confirmedInExecutionCount())
                ->description('قيد التنفيذ')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url(OrderManagementResource::getUrl('index', ['tab' => OrderStatus::Confirmed->value])),
            Stat::make('تسليم جزئي', $statusCounts[OrderStatus::PartiallyDelivered->value] ?? 0)
                ->description('تم تسليم جزء من الكمية')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning')
                ->url(OrderManagementResource::getUrl('index', ['tab' => OrderStatus::PartiallyDelivered->value])),
            Stat::make('طلبات مُسلَّمة', $statusCounts[OrderStatus::Delivered->value] ?? 0)
                ->description('مكتملة بالكامل')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray')
                ->url(OrderResource::getUrl('index')),
            Stat::make('المطلوب للتشغيل', OrderItem::outstandingQuantityTotal())
                ->description('من الطلبات المؤكدة وقيد التسليم')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('danger')
                ->url(ProductionRequirements::getUrl()),
            Stat::make('منتجات نشطة', Product::query()->where('active', true)->count())
                ->description('ظاهرة في المتجر')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info')
                ->url('/admin/products?'.http_build_query(['filters' => ['active' => ['value' => 'true']]])),
            Stat::make('العملاء', Customer::query()->count())
                ->description('إجمالي العملاء')
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
