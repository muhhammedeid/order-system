<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemException;
use App\Models\OrderTransitionException;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemEditingTest extends TestCase
{
    use RefreshDatabase;

    private function newVariant(array $productAttributes = [], array $variantAttributes = []): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->create([
                'price_visibility' => 'public',
                'price' => 100,
                'size_enabled' => true,
                ...$productAttributes,
            ]))
            ->create([
                'color' => 'Black',
                'size' => '41',
                'available_quantity' => 0,
                ...$variantAttributes,
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

    private function orderItem(Order $order, ProductVariant $variant, int $quantity = 5): OrderItem
    {
        return $order->items()->create(
            OrderItem::snapshotFromVariant($variant) + ['quantity' => $quantity]
        );
    }

    public function test_quantity_only_edit_preserves_snapshots(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant(productAttributes: ['name' => 'Original Name', 'product_code' => 'OR-1']);
        $item = $this->orderItem($order, $variant, 5);
        $order->recalculateTotalQuantity();

        $originalSnapshots = [
            'product_code' => $item->product_code,
            'product_name' => $item->product_name,
            'color' => $item->color,
            'size' => $item->size,
            'unit_price' => $item->unit_price,
            'price_visibility' => $item->price_visibility,
        ];

        // Product data changes after the order was submitted.
        $variant->product->update(['name' => 'Renamed Later', 'product_code' => 'OR-1X', 'price' => 999]);

        $order->updateItems([
            ['id' => $item->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 9],
        ]);

        $item->refresh();

        $this->assertSame(9, $item->quantity);
        $this->assertSame($originalSnapshots, [
            'product_code' => $item->product_code,
            'product_name' => $item->product_name,
            'color' => $item->color,
            'size' => $item->size,
            'unit_price' => $item->unit_price,
            'price_visibility' => $item->price_visibility,
        ]);
        $this->assertSame(9, $order->refresh()->total_quantity);
    }

    public function test_variant_change_rebuilds_trusted_snapshots(): void
    {
        $order = $this->newOrder();
        $originalVariant = $this->newVariant(productAttributes: ['name' => 'Public Shoe', 'product_code' => 'PB-1']);
        $item = $this->orderItem($order, $originalVariant, 5);
        $order->recalculateTotalQuantity();

        $secretProduct = Product::factory()->requestPrice()->create([
            'name' => 'Secret Shoe',
            'product_code' => 'SC-9',
            'price' => 1234.56,
            'size_enabled' => true,
        ]);

        $secretVariant = $secretProduct->variants()->create([
            'color' => 'White',
            'size' => '42',
            'available_quantity' => 3,
        ]);

        $order->updateItems([
            ['id' => $item->id, 'product_id' => $secretProduct->id, 'product_variant_id' => $secretVariant->id, 'quantity' => 2],
        ]);

        $item->refresh();

        $this->assertSame($secretProduct->id, $item->product_id);
        $this->assertSame($secretVariant->id, $item->product_variant_id);
        $this->assertSame('SC-9', $item->product_code);
        $this->assertSame('Secret Shoe', $item->product_name);
        $this->assertSame('White', $item->color);
        $this->assertSame('42', $item->size);
        $this->assertNull($item->unit_price);
        $this->assertSame('request_price', $item->price_visibility);
    }

    public function test_new_item_from_hidden_price_product_stores_no_unit_price(): void
    {
        $order = $this->newOrder();
        $variantA = $this->newVariant();
        $itemA = $this->orderItem($order, $variantA, 5);
        $order->recalculateTotalQuantity();

        $secretProduct = Product::factory()->requestPrice()->create([
            'name' => 'Hidden Price Shoe',
            'product_code' => 'HP-7',
            'price' => 4321.98,
            'size_enabled' => true,
        ]);

        $secretVariant = $secretProduct->variants()->create([
            'color' => 'Navy',
            'size' => '43',
            'available_quantity' => 0,
        ]);

        $order->updateItems([
            ['id' => $itemA->id, 'product_id' => $variantA->product_id, 'product_variant_id' => $variantA->id, 'quantity' => 5],
            ['product_id' => $secretProduct->id, 'product_variant_id' => $secretVariant->id, 'quantity' => 1],
        ]);

        $newItem = $order->items()->where('product_code', 'HP-7')->firstOrFail();

        $this->assertNull($newItem->unit_price);
        $this->assertSame('request_price', $newItem->price_visibility);
        $this->assertSame(6, $order->refresh()->total_quantity);
    }

    public function test_adding_and_removing_items_recalculates_total(): void
    {
        $order = $this->newOrder();
        $variantA = $this->newVariant();
        $itemA = $this->orderItem($order, $variantA, 5);
        $order->recalculateTotalQuantity();

        $variantB = $this->newVariant(productAttributes: ['name' => 'Second Shoe', 'product_code' => 'SD-2']);

        $order->updateItems([
            ['id' => $itemA->id, 'product_id' => $variantA->product_id, 'product_variant_id' => $variantA->id, 'quantity' => 5],
            ['product_id' => $variantB->product_id, 'product_variant_id' => $variantB->id, 'quantity' => 3],
        ]);

        $this->assertSame(2, $order->items()->count());
        $this->assertSame(8, $order->refresh()->total_quantity);

        $order->updateItems([
            ['product_id' => $variantB->product_id, 'product_variant_id' => $variantB->id, 'quantity' => 3],
        ]);

        $this->assertSame(1, $order->items()->count());
        $this->assertSame(3, $order->refresh()->total_quantity);
        $this->assertDatabaseMissing('order_items', ['id' => $itemA->id]);
    }

    public function test_existing_item_id_from_another_order_is_rejected(): void
    {
        $orderA = $this->newOrder();
        $variantA = $this->newVariant();
        $this->orderItem($orderA, $variantA, 5);

        $orderB = $this->newOrder();
        $variantB = $this->newVariant();
        $foreignItem = $this->orderItem($orderB, $variantB, 7);
        $orderB->recalculateTotalQuantity();

        try {
            $orderA->updateItems([
                ['id' => $foreignItem->id, 'product_id' => $variantB->product_id, 'product_variant_id' => $variantB->id, 'quantity' => 1],
            ]);
            $this->fail('Expected OrderItemException for a cross-order item id');
        } catch (OrderItemException) {
        }

        $this->assertSame(7, $foreignItem->refresh()->quantity);
        $this->assertSame($orderB->id, $foreignItem->order_id);
        $this->assertSame(1, $orderA->items()->count());
        $this->assertSame(5, $orderA->items()->first()->quantity);
    }

    public function test_duplicate_item_ids_are_rejected(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant();
        $item = $this->orderItem($order, $variant, 5);
        $order->recalculateTotalQuantity();

        try {
            $order->updateItems([
                ['id' => $item->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 1],
                ['id' => $item->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 2],
            ]);
            $this->fail('Expected OrderItemException for duplicate ids');
        } catch (OrderItemException) {
        }

        $this->assertSame(5, $item->refresh()->quantity);
    }

    public function test_unknown_item_ids_are_rejected(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant();
        $item = $this->orderItem($order, $variant, 5);
        $order->recalculateTotalQuantity();

        try {
            $order->updateItems([
                ['id' => $item->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 1],
                ['id' => 999999, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 1],
            ]);
            $this->fail('Expected OrderItemException for an unknown id');
        } catch (OrderItemException) {
        }

        $this->assertSame(5, $item->refresh()->quantity);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_mismatched_product_and_variant_are_rejected(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant();
        $item = $this->orderItem($order, $variant, 5);
        $order->recalculateTotalQuantity();

        $otherProduct = Product::factory()->create(['size_enabled' => true]);

        try {
            $order->updateItems([
                ['id' => $item->id, 'product_id' => $otherProduct->id, 'product_variant_id' => $variant->id, 'quantity' => 4],
            ]);
            $this->fail('Expected OrderItemException for a mismatched product/variant pair');
        } catch (OrderItemException) {
        }

        $this->assertSame(5, $item->refresh()->quantity);
    }

    public function test_delivered_quantity_in_the_payload_is_ignored(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant();
        $item = $this->orderItem($order, $variant, 5);
        $order->recalculateTotalQuantity();

        $order->updateItems([
            [
                'id' => $item->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => 5,
                'delivered_quantity' => 4,
            ],
        ]);

        $this->assertSame(0, $item->refresh()->delivered_quantity);
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant();
        $item = $this->orderItem($order, $variant, 5);
        $order->recalculateTotalQuantity();

        foreach ([0, -1, 1.5, 'abc', null] as $quantity) {
            try {
                $order->updateItems([
                    ['id' => $item->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => $quantity],
                ]);
                $this->fail('Expected OrderItemException for an invalid quantity');
            } catch (OrderItemException) {
            }
        }

        $this->assertSame(5, $item->refresh()->quantity);
    }

    public function test_empty_item_set_is_rejected(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant();
        $this->orderItem($order, $variant, 5);

        $this->expectException(OrderItemException::class);

        $order->updateItems([]);
    }

    public function test_editing_is_rejected_after_confirmation_or_cancellation(): void
    {
        $order = $this->newOrder();
        $variant = $this->newVariant();
        $item = $this->orderItem($order, $variant, 5);
        $order->recalculateTotalQuantity();

        $order->confirm();

        try {
            $order->updateItems([
                ['id' => $item->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 1],
            ]);
            $this->fail('Expected OrderTransitionException for a confirmed order');
        } catch (OrderTransitionException) {
        }

        $cancelled = $this->newOrder();
        $cancelledVariant = $this->newVariant();
        $cancelledItem = $this->orderItem($cancelled, $cancelledVariant, 2);
        $cancelled->recalculateTotalQuantity();
        $cancelled->cancel();

        try {
            $cancelled->updateItems([
                ['id' => $cancelledItem->id, 'product_id' => $cancelledVariant->product_id, 'product_variant_id' => $cancelledVariant->id, 'quantity' => 1],
            ]);
            $this->fail('Expected OrderTransitionException for a cancelled order');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(5, $item->refresh()->quantity);
        $this->assertSame(2, $cancelledItem->refresh()->quantity);
    }

    public function test_unsized_variant_edit_keeps_null_size(): void
    {
        $order = $this->newOrder();

        $unsizedProduct = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 250,
            'size_enabled' => false,
        ]);
        $unsizedVariant = $unsizedProduct->variants()->create([
            'color' => 'Brown',
            'size' => null,
            'available_quantity' => 0,
        ]);

        $item = $this->orderItem($order, $unsizedVariant, 2);
        $order->recalculateTotalQuantity();

        $order->updateItems([
            ['id' => $item->id, 'product_id' => $unsizedProduct->id, 'product_variant_id' => $unsizedVariant->id, 'quantity' => 6],
        ]);

        $item->refresh();

        $this->assertNull($item->size);
        $this->assertSame(6, $item->quantity);
        $this->assertSame('Brown', $item->color);
    }

    public function test_uncolored_variant_edit_keeps_null_color(): void
    {
        $order = $this->newOrder();

        $uncoloredProduct = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 250,
            'color_enabled' => false,
            'size_enabled' => true,
        ]);
        $uncoloredVariant = $uncoloredProduct->variants()->create([
            'color' => null,
            'size' => '40',
            'available_quantity' => 0,
        ]);

        $item = $this->orderItem($order, $uncoloredVariant, 2);
        $order->recalculateTotalQuantity();

        $order->updateItems([
            ['id' => $item->id, 'product_id' => $uncoloredProduct->id, 'product_variant_id' => $uncoloredVariant->id, 'quantity' => 6],
        ]);

        $item->refresh();

        $this->assertNull($item->color);
        $this->assertSame('40', $item->size);
        $this->assertSame(6, $item->quantity);
    }
}
