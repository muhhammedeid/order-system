<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

trait CreatesTestOrders
{
    /**
     * @param  array<string, mixed>  $customerOverrides
     * @param  array<string, mixed>  $orderOverrides
     */
    protected function createOrderWithCustomer(array $customerOverrides = [], array $orderOverrides = [], int $quantity = 10, int $delivered = 0): Order
    {
        $customer = Customer::factory()->create(array_merge([
            'name' => 'Order Customer',
            'phone' => '01000000000',
            'whatsapp' => null,
        ], $customerOverrides));

        $status = $orderOverrides['status'] ?? null;
        unset($orderOverrides['status']);

        $order = Order::create(array_merge([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => $customer->id,
            'total_quantity' => 0,
        ], $orderOverrides));

        if ($quantity > 0) {
            $product = Product::factory()->create(['size_enabled' => true]);
            $variant = $product->variants()->create([
                'color' => 'Black',
                'size' => '41',
                'available_quantity' => 100,
            ]);

            $item = $order->items()->create(OrderItem::snapshotFromVariant($variant) + [
                'quantity' => $quantity,
            ]);

            if ($delivered > 0) {
                $item->forceFill(['delivered_quantity' => $delivered])->save();
            }

            $order->recalculateTotalQuantity();
        }

        if ($status !== null) {
            $order->forceFill(['status' => $status])->save();
        }

        return $order->refresh();
    }
}
