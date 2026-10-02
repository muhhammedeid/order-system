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

        return __('filament.orders.review_title', ['order' => $order->order_number]);
    }

    public function getBreadcrumb(): string
    {
        return __('filament.orders.review_breadcrumb');
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
            OrderStatus::New => __('filament.orders.callout.new_title'),
            OrderStatus::Confirmed => __('filament.orders.callout.confirmed_title'),
            OrderStatus::PartiallyDelivered => __('filament.orders.callout.partial_title'),
            OrderStatus::Delivered => __('filament.orders.callout.delivered_title'),
            OrderStatus::Cancelled => __('filament.orders.callout.cancelled_title'),
        })->description(match ($order->status) {
            OrderStatus::New => __('filament.orders.callout.new_description'),
            OrderStatus::Confirmed => __('filament.orders.callout.confirmed_description'),
            OrderStatus::PartiallyDelivered => __('filament.orders.callout.partial_description'),
            OrderStatus::Delivered => __('filament.orders.callout.delivered_description'),
            OrderStatus::Cancelled => __('filament.orders.callout.cancelled_description'),
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
