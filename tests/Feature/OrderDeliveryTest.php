<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDeliveryException;
use App\Models\OrderItem;
use App\Models\OrderTransitionException;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function newVariant(string $color = 'Black', string $size = '41'): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->create([
                'price_visibility' => 'public',
                'price' => 100,
                'size_enabled' => true,
            ]))
            ->create([
                'color' => $color,
                'size' => $size,
                'available_quantity' => 0,
            ]);
    }

    private function newOrder(): Order
    {
        return Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);
    }

    /**
     * @return array{0: Order, 1: OrderItem, 2: OrderItem}
     */
    private function confirmedOrder(int $quantityA = 5, int $quantityB = 3): array
    {
        $order = $this->newOrder();

        $itemA = $order->items()->create(
            OrderItem::snapshotFromVariant($this->newVariant('Black', '41')) + ['quantity' => $quantityA]
        );
        $itemB = $order->items()->create(
            OrderItem::snapshotFromVariant($this->newVariant('White', '42')) + ['quantity' => $quantityB]
        );

        $order->recalculateTotalQuantity();
        $order->confirm();
        $order->refresh();

        return [$order, $itemA, $itemB];
    }

    public function test_partial_delivery_sets_partially_delivered(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        $order->recordDeliveries([
            $itemA->id => ['quantity' => 2, 'expected_delivered' => 0],
        ]);

        $this->assertSame(OrderStatus::PartiallyDelivered, $order->status);
        $this->assertSame(2, $itemA->refresh()->delivered_quantity);
        $this->assertSame(3, $itemA->remaining_quantity);
        $this->assertSame(0, $itemB->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::PartiallyDelivered->value, $order->fresh()->status->value);
    }

    public function test_repeated_partial_deliveries_accumulate_until_completion(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        $order->recordDeliveries([
            $itemA->id => ['quantity' => 2, 'expected_delivered' => 0],
        ]);

        $order->recordDeliveries([
            $itemA->id => ['quantity' => 3, 'expected_delivered' => 2],
        ]);

        $this->assertSame(5, $itemA->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::PartiallyDelivered, $order->status);

        $order->recordDeliveries([
            $itemB->id => ['quantity' => 3, 'expected_delivered' => 0],
        ]);

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(0, $itemA->refresh()->remaining_quantity);
        $this->assertSame(0, $itemB->refresh()->remaining_quantity);
    }

    public function test_zero_only_submission_is_rejected_without_changes(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        try {
            $order->recordDeliveries([
                $itemA->id => ['quantity' => 0, 'expected_delivered' => 0],
                $itemB->id => ['quantity' => 0, 'expected_delivered' => 0],
            ]);
            $this->fail('Expected OrderDeliveryException for a zero-only submission');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(0, $itemA->refresh()->delivered_quantity);
        $this->assertSame(0, $itemB->refresh()->delivered_quantity);
    }

    public function test_negative_and_non_integer_quantities_are_rejected(): void
    {
        [$order, $itemA] = $this->confirmedOrder();

        foreach ([-1, 1.5, 'abc', null] as $quantity) {
            try {
                $order->recordDeliveries([
                    $itemA->id => ['quantity' => $quantity, 'expected_delivered' => 0],
                ]);
                $this->fail('Expected OrderDeliveryException for an invalid quantity');
            } catch (OrderDeliveryException) {
            }
        }

        $this->assertSame(0, $itemA->refresh()->delivered_quantity);
    }

    public function test_over_delivery_is_rejected(): void
    {
        [$order, $itemA] = $this->confirmedOrder();

        try {
            $order->recordDeliveries([
                $itemA->id => ['quantity' => 6, 'expected_delivered' => 0],
            ]);
            $this->fail('Expected OrderDeliveryException for over-delivery');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(0, $itemA->refresh()->delivered_quantity);
    }

    public function test_mixed_item_over_delivery_rolls_back_everything(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        try {
            $order->recordDeliveries([
                $itemA->id => ['quantity' => 2, 'expected_delivered' => 0],
                $itemB->id => ['quantity' => 4, 'expected_delivered' => 0],
            ]);
            $this->fail('Expected OrderDeliveryException for the over-delivered item');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(0, $itemA->refresh()->delivered_quantity);
        $this->assertSame(0, $itemB->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    public function test_delivered_quantity_payload_key_is_ignored(): void
    {
        [$order, $itemA] = $this->confirmedOrder();

        $order->recordDeliveries([
            $itemA->id => [
                'quantity' => 2,
                'expected_delivered' => 0,
                'delivered_quantity' => 99,
            ],
        ]);

        $this->assertSame(2, $itemA->refresh()->delivered_quantity);
    }

    public function test_stale_expected_delivered_is_rejected(): void
    {
        [$order, $itemA] = $this->confirmedOrder();

        $order->recordDeliveries([
            $itemA->id => ['quantity' => 1, 'expected_delivered' => 0],
        ]);

        try {
            $order->recordDeliveries([
                $itemA->id => ['quantity' => 1, 'expected_delivered' => 0],
            ]);
            $this->fail('Expected OrderDeliveryException for a stale submission');
        } catch (OrderDeliveryException $exception) {
            $this->assertStringContainsString('جلسة أخرى', $exception->getMessage());
        }

        $this->assertSame(1, $itemA->refresh()->delivered_quantity);
    }

    public function test_missing_expected_delivered_is_rejected(): void
    {
        [$order, $itemA] = $this->confirmedOrder();

        try {
            $order->recordDeliveries([
                $itemA->id => ['quantity' => 1],
            ]);
            $this->fail('Expected OrderDeliveryException for a missing expected value');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(0, $itemA->refresh()->delivered_quantity);
    }

    public function test_unknown_item_id_is_rejected(): void
    {
        [$order] = $this->confirmedOrder();

        try {
            $order->recordDeliveries([
                999999 => ['quantity' => 1, 'expected_delivered' => 0],
            ]);
            $this->fail('Expected OrderDeliveryException for an unknown item id');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    public function test_delivery_is_rejected_for_invalid_source_states(): void
    {
        $newOrder = $this->newOrder();
        $variant = $this->newVariant();
        $newItem = $newOrder->items()->create(
            OrderItem::snapshotFromVariant($variant) + ['quantity' => 4]
        );
        $newOrder->recalculateTotalQuantity();

        try {
            $newOrder->recordDeliveries([
                $newItem->id => ['quantity' => 1, 'expected_delivered' => 0],
            ]);
            $this->fail('Expected OrderTransitionException for a new order');
        } catch (OrderTransitionException) {
        }

        $cancelledOrder = $this->newOrder();
        $cancelledVariant = $this->newVariant();
        $cancelledItem = $cancelledOrder->items()->create(
            OrderItem::snapshotFromVariant($cancelledVariant) + ['quantity' => 2]
        );
        $cancelledOrder->recalculateTotalQuantity();
        $cancelledOrder->cancel();

        try {
            $cancelledOrder->recordDeliveries([
                $cancelledItem->id => ['quantity' => 1, 'expected_delivered' => 0],
            ]);
            $this->fail('Expected OrderTransitionException for a cancelled order');
        } catch (OrderTransitionException) {
        }

        [$deliveredOrder, $deliveredItem] = $this->confirmedOrder();
        $deliveredOrder->deliverAllRemaining();

        try {
            $deliveredOrder->recordDeliveries([
                $deliveredItem->id => ['quantity' => 1, 'expected_delivered' => (int) $deliveredItem->quantity],
            ]);
            $this->fail('Expected OrderTransitionException for a delivered order');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(OrderStatus::Delivered, $deliveredOrder->status);
    }

    public function test_deliver_all_remaining_marks_everything_delivered(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        $order->deliverAllRemaining();

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(5, $itemA->refresh()->delivered_quantity);
        $this->assertSame(3, $itemB->refresh()->delivered_quantity);
        $this->assertSame(0, $itemA->remaining_quantity);
        $this->assertSame(0, $itemB->remaining_quantity);
    }

    public function test_deliver_all_remaining_uses_locked_current_database_values(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        // Another session records a partial delivery after this instance was loaded.
        Order::query()->whereKey($order->id)->firstOrFail()->recordDeliveries([
            $itemA->id => ['quantity' => 2, 'expected_delivered' => 0],
        ]);

        $order->deliverAllRemaining();

        $this->assertSame(5, $itemA->refresh()->delivered_quantity);
        $this->assertSame(3, $itemB->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::Delivered, $order->status);
    }

    public function test_repeated_deliver_all_is_rejected(): void
    {
        [$order] = $this->confirmedOrder();

        $order->deliverAllRemaining();

        try {
            $order->deliverAllRemaining();
            $this->fail('Expected OrderTransitionException on a repeated deliver-all');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(OrderStatus::Delivered, $order->status);
    }
}
