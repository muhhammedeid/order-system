<?php

namespace App\Support\WhatsApp\Order;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Predefined operational order messages. Presentation-only: the text lives in
 * translations and is composed from the order number, the customer name and
 * the approved delivery totals. Internal fields (admin notes, customer notes,
 * production aggregates, prices) are never included.
 *
 * These are not the future template/campaign system and are not stored.
 */
class OrderMessageTemplates
{
    public const ORDER_RECEIVED = 'order_received';

    public const ORDER_CONFIRMED = 'order_confirmed';

    public const PARTIAL_DELIVERY = 'partial_delivery';

    public const ORDER_DELIVERED = 'order_delivered';

    public const ORDER_CANCELLED = 'order_cancelled';

    /**
     * @return array<int, array{key: string, label: string, body: string}>
     */
    public static function for(Order $order): array
    {
        $key = self::keyFor($order);

        return [[
            'key' => $key,
            'label' => __("admin.whatsapp.order_messages.{$key}.label"),
            'body' => __("admin.whatsapp.order_messages.{$key}.body", self::replacements($order)),
        ]];
    }

    public static function isAvailable(string $key, Order $order): bool
    {
        return $key === self::keyFor($order);
    }

    public static function keyFor(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::New => self::ORDER_RECEIVED,
            OrderStatus::Confirmed => self::ORDER_CONFIRMED,
            OrderStatus::PartiallyDelivered => self::PARTIAL_DELIVERY,
            OrderStatus::Delivered => self::ORDER_DELIVERED,
            OrderStatus::Cancelled => self::ORDER_CANCELLED,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function replacements(Order $order): array
    {
        $delivered = (int) $order->items->sum('delivered_quantity');
        $total = (int) $order->total_quantity;

        return [
            'order_number' => (string) $order->order_number,
            'customer' => (string) ($order->customer?->name ?? ''),
            'delivered' => number_format($delivered),
            'remaining' => number_format(max(0, $total - $delivered)),
        ];
    }
}
