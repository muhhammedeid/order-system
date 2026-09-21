<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDeliveryException;
use App\Models\OrderItem;
use App\Models\OrderItemColorQuantity;
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

    /**
     * Confirmed order with one multi-color line: requested quantity per
     * color, physical total, and a joined color snapshot.
     *
     * @param  array<int, string>  $colors
     * @return array{0: Order, 1: OrderItem}
     */
    private function multiColorOrder(int $requestedPerColor = 5, array $colors = ['Black', 'White', 'Beige']): array
    {
        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 100,
            'size_enabled' => false,
        ]);

        $variants = collect($colors)->map(fn (string $color): ProductVariant => ProductVariant::factory()
            ->for($product)
            ->create([
                'color' => $color,
                'size' => '37',
                'available_quantity' => 0,
            ]));

        $order = $this->newOrder();

        $snapshot = OrderItem::snapshotFromVariant($variants->first());
        $snapshot['color'] = implode('، ', $colors);

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => $requestedPerColor,
            'color_count' => count($colors),
            'quantity' => $requestedPerColor * count($colors),
        ]);

        $order->recalculateTotalQuantity();
        $order->confirm();
        $order->refresh();

        return [$order, $item->refresh()];
    }

    private function colorRow(OrderItem $item, string $color): OrderItemColorQuantity
    {
        return OrderItemColorQuantity::query()
            ->where('order_item_id', $item->id)
            ->where('color', $color)
            ->firstOrFail();
    }

    /**
     * @return array{quantity: mixed, expected_delivered: mixed}
     */
    private function submission(mixed $quantity, int $expected = 0): array
    {
        return ['quantity' => $quantity, 'expected_delivered' => $expected];
    }

    public function test_partial_delivery_records_one_color_only(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');

        $order->recordDeliveries([$black->id => $this->submission(2)]);

        $this->assertSame(2, $black->refresh()->delivered_quantity);
        $this->assertSame(3, $black->remaining_quantity);
        $this->assertSame(0, $this->colorRow($item, 'White')->delivered_quantity);
        $this->assertSame(5, $this->colorRow($item, 'White')->remaining_quantity);
        $this->assertSame(0, $this->colorRow($item, 'Beige')->delivered_quantity);
        $this->assertSame(2, $item->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::PartiallyDelivered, $order->status);
    }

    public function test_one_submission_can_deliver_different_quantities_per_color(): void
    {
        [$order, $item] = $this->multiColorOrder(10);

        $black = $this->colorRow($item, 'Black');
        $white = $this->colorRow($item, 'White');

        $order->recordDeliveries([
            $black->id => $this->submission(10, 0),
            $white->id => $this->submission(5, 0),
        ]);

        $this->assertSame(10, $black->refresh()->delivered_quantity);
        $this->assertSame(0, $black->remaining_quantity);
        $this->assertSame(5, $white->refresh()->delivered_quantity);
        $this->assertSame(5, $white->remaining_quantity);
        $this->assertSame(0, $this->colorRow($item, 'Beige')->delivered_quantity);
        $this->assertSame(15, $item->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::PartiallyDelivered, $order->status);
    }

    public function test_delivered_aggregate_equals_the_sum_of_color_deliveries(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $order->recordDeliveries([
            $this->colorRow($item, 'Black')->id => $this->submission(4),
            $this->colorRow($item, 'Beige')->id => $this->submission(1),
        ]);

        $this->assertSame(5, $item->refresh()->delivered_quantity);
        $this->assertSame(10, $item->remaining_quantity);
        $this->assertSame(
            (int) $item->colorQuantities()->sum('delivered_quantity'),
            (int) $item->delivered_quantity,
        );
    }

    public function test_repeated_partial_deliveries_accumulate_per_color_until_completion(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        $blackA = $this->colorRow($itemA, 'Black');
        $whiteB = $this->colorRow($itemB, 'White');

        $order->recordDeliveries([$blackA->id => $this->submission(2)]);

        $order->recordDeliveries([$blackA->id => $this->submission(3, 2)]);

        $this->assertSame(5, $blackA->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::PartiallyDelivered, $order->status);

        $order->recordDeliveries([$whiteB->id => $this->submission(3)]);

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(0, $blackA->refresh()->remaining_quantity);
        $this->assertSame(0, $whiteB->refresh()->remaining_quantity);
    }

    public function test_delivery_exceeding_one_color_remaining_quantity_is_rejected(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');

        try {
            $order->recordDeliveries([$black->id => $this->submission(6)]);
            $this->fail('Expected OrderDeliveryException for an over-delivery');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(0, $black->refresh()->delivered_quantity);
        $this->assertSame(0, $item->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    public function test_invalid_color_rolls_back_every_other_color_update(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');
        $white = $this->colorRow($item, 'White');

        try {
            $order->recordDeliveries([
                $black->id => $this->submission(2),
                $white->id => $this->submission(9),
            ]);
            $this->fail('Expected OrderDeliveryException for the invalid color');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(0, $black->refresh()->delivered_quantity);
        $this->assertSame(0, $white->refresh()->delivered_quantity);
        $this->assertSame(0, $item->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    public function test_zero_only_submission_is_rejected_without_changes(): void
    {
        [$order, $item] = $this->multiColorOrder();

        try {
            $order->recordDeliveries([
                $this->colorRow($item, 'Black')->id => $this->submission(0),
                $this->colorRow($item, 'White')->id => $this->submission(0),
            ]);
            $this->fail('Expected OrderDeliveryException for a zero-only submission');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(0, $item->refresh()->delivered_quantity);
    }

    public function test_negative_and_non_integer_quantities_are_rejected(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');

        foreach ([-1, 1.5, 'abc', null] as $quantity) {
            try {
                $order->recordDeliveries([$black->id => $this->submission($quantity)]);
                $this->fail('Expected OrderDeliveryException for an invalid quantity');
            } catch (OrderDeliveryException) {
            }
        }

        $this->assertSame(0, $black->refresh()->delivered_quantity);
    }

    public function test_delivered_quantity_payload_key_is_ignored(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');

        $order->recordDeliveries([
            $black->id => [
                'quantity' => 2,
                'expected_delivered' => 0,
                'delivered_quantity' => 99,
            ],
        ]);

        $this->assertSame(2, $black->refresh()->delivered_quantity);
        $this->assertSame(2, $item->refresh()->delivered_quantity);
    }

    public function test_stale_expected_delivered_is_rejected(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');

        $order->recordDeliveries([$black->id => $this->submission(1)]);

        try {
            $order->recordDeliveries([$black->id => $this->submission(1, 0)]);
            $this->fail('Expected OrderDeliveryException for a stale submission');
        } catch (OrderDeliveryException $exception) {
            $this->assertStringContainsString('جلسة أخرى', $exception->getMessage());
        }

        $this->assertSame(1, $black->refresh()->delivered_quantity);
    }

    public function test_missing_expected_delivered_is_rejected(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');

        try {
            $order->recordDeliveries([$black->id => ['quantity' => 1]]);
            $this->fail('Expected OrderDeliveryException for a missing expected value');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(0, $black->refresh()->delivered_quantity);
    }

    public function test_unknown_color_row_id_is_rejected(): void
    {
        [$order] = $this->confirmedOrder();

        try {
            $order->recordDeliveries([999999 => $this->submission(1)]);
            $this->fail('Expected OrderDeliveryException for an unknown color row id');
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
            $newOrder->recordDeliveries([$this->colorRow($newItem, 'Black')->id => $this->submission(1)]);
            $this->fail('Expected OrderTransitionException for a new order');
        } catch (OrderTransitionException) {
        }

        $cancelledOrder = $this->newOrder();
        $cancelledItem = $cancelledOrder->items()->create(
            OrderItem::snapshotFromVariant($this->newVariant()) + ['quantity' => 2]
        );
        $cancelledOrder->recalculateTotalQuantity();
        $cancelledOrder->cancel();

        try {
            $cancelledOrder->recordDeliveries([$this->colorRow($cancelledItem, 'Black')->id => $this->submission(1)]);
            $this->fail('Expected OrderTransitionException for a cancelled order');
        } catch (OrderTransitionException) {
        }

        [$deliveredOrder, $deliveredItem] = $this->confirmedOrder();
        $deliveredOrder->deliverAllRemaining();

        try {
            $deliveredOrder->recordDeliveries([
                $this->colorRow($deliveredItem, 'Black')->id => $this->submission(1),
            ]);
            $this->fail('Expected OrderTransitionException for a delivered order');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(OrderStatus::Delivered, $deliveredOrder->status);
    }

    public function test_deliver_all_remaining_completes_every_color(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $order->deliverAllRemaining();

        $this->assertSame(OrderStatus::Delivered, $order->status);

        foreach ($item->colorQuantities()->get() as $colorRow) {
            $this->assertSame(0, $colorRow->remaining_quantity);
            $this->assertSame($colorRow->requested_quantity, $colorRow->delivered_quantity);
        }

        $this->assertSame(15, $item->refresh()->delivered_quantity);
        $this->assertSame(0, $item->remaining_quantity);
    }

    public function test_repeated_deliver_all_is_rejected(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $order->deliverAllRemaining();

        try {
            $order->deliverAllRemaining();
            $this->fail('Expected OrderTransitionException on a repeated deliver-all');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(OrderStatus::Delivered, $order->status);
    }

    public function test_unallocated_historical_delivery_blocks_partial_delivery_until_reconciled(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $item->forceFill(['unallocated_delivered_quantity' => 2])->save();
        $item->refresh();

        $black = $this->colorRow($item, 'Black');

        try {
            $order->recordDeliveries([$black->id => $this->submission(1)]);
            $this->fail('Expected OrderDeliveryException while unallocated deliveries exist');
        } catch (OrderDeliveryException $exception) {
            $this->assertStringContainsString('تسوية', $exception->getMessage());
        }

        try {
            $order->deliverAllRemaining();
            $this->fail('Expected OrderDeliveryException for deliver-all while unallocated deliveries exist');
        } catch (OrderDeliveryException) {
        }

        $this->assertTrue($order->fresh()->hasUnallocatedDeliveries());
        $this->assertSame(0, $black->refresh()->delivered_quantity);
    }

    public function test_reconciliation_allocates_unallocated_pieces_without_changing_totals(): void
    {
        [$order, $item] = $this->multiColorOrder();

        // Historical state: 4 pieces delivered, not attributable by rule.
        $item->forceFill([
            'delivered_quantity' => 4,
            'unallocated_delivered_quantity' => 4,
        ])->save();
        $item->refresh();

        $black = $this->colorRow($item, 'Black');
        $white = $this->colorRow($item, 'White');

        $order->reconcileUnallocatedDeliveries([
            $black->id => 3,
            $white->id => 1,
        ]);

        $item->refresh();

        $this->assertSame(3, $black->refresh()->delivered_quantity);
        $this->assertSame(1, $white->refresh()->delivered_quantity);
        $this->assertSame(0, (int) $item->unallocated_delivered_quantity);
        $this->assertSame(4, (int) $item->delivered_quantity);
        $this->assertFalse($item->hasUnallocatedDeliveries());

        // Deliveries work again after the reconciliation.
        $order->recordDeliveries([$black->id => $this->submission(1, 3)]);

        $this->assertSame(4, $black->refresh()->delivered_quantity);
        $this->assertSame(5, $item->refresh()->delivered_quantity);
    }

    public function test_reconciliation_rejects_an_incomplete_or_excessive_allocation(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $item->forceFill([
            'delivered_quantity' => 2,
            'unallocated_delivered_quantity' => 2,
        ])->save();
        $item->refresh();

        $black = $this->colorRow($item, 'Black');

        try {
            $order->reconcileUnallocatedDeliveries([$black->id => 1]);
            $this->fail('Expected OrderDeliveryException for an incomplete allocation');
        } catch (OrderDeliveryException) {
        }

        try {
            $order->reconcileUnallocatedDeliveries([$black->id => 7]);
            $this->fail('Expected OrderDeliveryException for an excessive allocation');
        } catch (OrderDeliveryException) {
        }

        $item->refresh();

        $this->assertSame(0, $black->refresh()->delivered_quantity);
        $this->assertSame(2, (int) $item->unallocated_delivered_quantity);
    }

    public function test_reconciliation_rolls_back_every_item_when_one_allocation_is_invalid(): void
    {
        [$order, $firstItem] = $this->multiColorOrder();

        $secondItem = $order->items()->create(
            OrderItem::snapshotFromVariant($this->newVariant('Brown', '41')) + ['quantity' => 5]
        );

        $firstItem->forceFill([
            'delivered_quantity' => 2,
            'unallocated_delivered_quantity' => 2,
        ])->save();

        $secondItem->forceFill([
            'delivered_quantity' => 1,
            'unallocated_delivered_quantity' => 1,
        ])->save();

        try {
            $order->reconcileUnallocatedDeliveries([
                $this->colorRow($firstItem, 'Black')->id => 2,
                $this->colorRow($secondItem, 'Brown')->id => 0,
            ]);
            $this->fail('Expected OrderDeliveryException for the incomplete second item');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(0, $this->colorRow($firstItem, 'Black')->refresh()->delivered_quantity);
        $this->assertSame(2, (int) $firstItem->refresh()->unallocated_delivered_quantity);
        $this->assertSame(2, (int) $firstItem->delivered_quantity);
        $this->assertSame(1, (int) $secondItem->refresh()->unallocated_delivered_quantity);
    }

    public function test_delivery_rejects_a_color_row_that_belongs_to_another_order(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $otherOrder = $this->newOrder();
        $otherItem = $otherOrder->items()->create(
            OrderItem::snapshotFromVariant($this->newVariant('Green', '42')) + ['quantity' => 4]
        );
        $otherOrder->recalculateTotalQuantity();
        $otherOrder->confirm();

        $foreign = $this->colorRow($otherItem, 'Green');

        try {
            $order->recordDeliveries([$foreign->id => $this->submission(1)]);
            $this->fail('Expected OrderDeliveryException for a foreign color row');
        } catch (OrderDeliveryException) {
        }

        $this->assertSame(0, $foreign->refresh()->delivered_quantity);
        $this->assertSame(0, $item->refresh()->delivered_quantity);
    }

    public function test_deliver_all_remaining_uses_locked_current_database_values(): void
    {
        [$order, $itemA, $itemB] = $this->confirmedOrder();

        // Another session records a partial delivery after this instance was loaded.
        Order::query()->whereKey($order->id)->firstOrFail()->recordDeliveries([
            $this->colorRow($itemA, 'Black')->id => $this->submission(2),
        ]);

        $order->deliverAllRemaining();

        $this->assertSame(5, $this->colorRow($itemA, 'Black')->refresh()->delivered_quantity);
        $this->assertSame(3, $this->colorRow($itemB, 'White')->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::Delivered, $order->status);
    }
}
