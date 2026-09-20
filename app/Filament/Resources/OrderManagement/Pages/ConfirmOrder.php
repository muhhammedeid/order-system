<?php

namespace App\Filament\Resources\OrderManagement\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Filament\Resources\OrderManagement\OrderPrintAction;
use App\Filament\Resources\OrderManagement\OrderStatusActions;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Models\Order;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;

/**
 * Dedicated review-and-confirm page: the admin reviews the full order
 * (items, quantities, customer data, previous notes) and confirms it.
 * Stock quantities are an Admin reference only and never gate the order.
 */
class ConfirmOrder extends ViewRecord
{
    protected static string $resource = OrderManagementResource::class;

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return "مراجعة وتأكيد الطلب {$order->order_number}";
    }

    public function getBreadcrumb(): string
    {
        return 'مراجعة وتأكيد';
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderStatusActions::edit(),
            OrderStatusActions::confirm(),
            OrderStatusActions::cancel(),
            OrderPrintAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getReviewCallout(),
            $this->getInfolistContentComponent(),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configureReview($schema);
    }

    protected function getReviewCallout(): Callout
    {
        /** @var Order $order */
        $order = $this->getRecord();

        $callout = Callout::make(match ($order->status) {
            OrderStatus::New => 'الطلب في انتظار المراجعة',
            OrderStatus::Confirmed => 'تم تأكيد الطلب',
            OrderStatus::PartiallyDelivered => 'تم تسليم جزء من الطلب',
            OrderStatus::Delivered => 'تم تسليم الطلب',
            OrderStatus::Cancelled => 'الطلب ملغي',
        })->description(match ($order->status) {
            OrderStatus::New => 'راجع البنود والكميات وبيانات العميل، ثم اضغط «تأكيد الطلب» أعلى الصفحة. لا يؤثر التأكيد على كمية المخزون، ويمكنك تدوين ملاحظات الإدارة معه.',
            OrderStatus::Confirmed => 'الطلب مؤكد ودخل مرحلة التنفيذ. لا يمكن إلغاؤه، وتُتابَع كميات التسليم من شاشات إدارة الطلبات.',
            OrderStatus::PartiallyDelivered => 'تم تسجيل تسليم جزء من الكميات، وتُتابَع بقية الكميات من شاشات إدارة الطلبات.',
            OrderStatus::Delivered => 'حالة نهائية: تم تسليم كامل كميات الطلب.',
            OrderStatus::Cancelled => 'حالة نهائية: تم إلغاء الطلب.',
        });

        return match ($order->status) {
            OrderStatus::New => $callout->warning(),
            OrderStatus::Confirmed => $callout->success(),
            OrderStatus::PartiallyDelivered => $callout->info(),
            OrderStatus::Delivered => $callout->info(),
            OrderStatus::Cancelled => $callout->danger(),
        };
    }
}
