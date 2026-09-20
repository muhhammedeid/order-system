<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeliveredQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithItem(int $quantity = 5): Order
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['size_enabled' => true]))
            ->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 0]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => $quantity]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        return Order::query()->firstOrFail();
    }

    public function test_delivered_quantity_defaults_to_zero_and_remaining_is_derived(): void
    {
        $order = $this->orderWithItem(5);
        $item = $order->items()->firstOrFail();

        $this->assertSame(0, $item->delivered_quantity);
        $this->assertSame(5, $item->remaining_quantity);
        $this->assertTrue($item->order->is($order));
    }

    public function test_delivered_quantity_cannot_be_negative(): void
    {
        $item = $this->orderWithItem()->items()->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);

        $item->delivered_quantity = -1;
        $item->save();
    }

    public function test_delivered_quantity_cannot_exceed_ordered_quantity(): void
    {
        $item = $this->orderWithItem(4)->items()->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);

        $item->delivered_quantity = 5;
        $item->save();
    }

    public function test_set_delivered_quantity_updates_remaining_and_allows_boundaries(): void
    {
        $item = $this->orderWithItem(4)->items()->firstOrFail();

        $item->setDeliveredQuantity(0);
        $this->assertSame(4, $item->refresh()->remaining_quantity);

        $item->setDeliveredQuantity(2);
        $this->assertSame(2, $item->remaining_quantity);

        $item->setDeliveredQuantity(4);
        $this->assertSame(0, $item->remaining_quantity);
    }

    public function test_set_delivered_quantity_rejects_out_of_range_values(): void
    {
        $item = $this->orderWithItem(4)->items()->firstOrFail();

        try {
            $item->setDeliveredQuantity(-1);
            $this->fail('Expected InvalidArgumentException for a negative value');
        } catch (\InvalidArgumentException) {
        }

        try {
            $item->setDeliveredQuantity(5);
            $this->fail('Expected InvalidArgumentException for an over-delivery');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, $item->refresh()->delivered_quantity);
    }

    public function test_delivery_status_is_null_when_nothing_is_delivered(): void
    {
        $order = $this->orderWithItem();

        $this->assertNull($order->deriveDeliveryStatus());
    }

    public function test_delivery_status_is_partially_delivered_when_some_quantity_remains(): void
    {
        $order = $this->orderWithItem(5);
        $order->items()->firstOrFail()->setDeliveredQuantity(2);

        $this->assertSame(OrderStatus::PartiallyDelivered, $order->deriveDeliveryStatus());
    }

    public function test_delivery_status_is_delivered_when_all_items_are_complete(): void
    {
        $order = $this->orderWithItem(5);
        $order->items()->firstOrFail()->setDeliveredQuantity(5);

        $this->assertSame(OrderStatus::Delivered, $order->deriveDeliveryStatus());
    }

    public function test_delivery_status_requires_all_items_to_be_complete(): void
    {
        $order = $this->orderWithItem(2);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['size_enabled' => true]))
            ->create(['color' => 'White', 'size' => '41', 'available_quantity' => 0]);

        $order->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_code' => $variant->product->product_code,
            'product_name' => $variant->product->name,
            'color' => $variant->color,
            'size' => $variant->size,
            'quantity' => 3,
            'unit_price' => 100,
            'price_visibility' => 'public',
        ]);

        $items = $order->items()->orderBy('id')->get();
        $items[0]->setDeliveredQuantity(2);

        $this->assertSame(OrderStatus::PartiallyDelivered, $order->deriveDeliveryStatus());

        $items[1]->setDeliveredQuantity(3);

        $this->assertSame(OrderStatus::Delivered, $order->deriveDeliveryStatus());
    }

    public function test_order_status_enum_contains_only_the_approved_lifecycle(): void
    {
        $values = array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases());

        $this->assertSame(['new', 'confirmed', 'partially_delivered', 'delivered', 'cancelled'], $values);
        $this->assertNull(OrderStatus::tryFrom('exported'));
        $this->assertSame(
            ['new', 'confirmed', 'partially_delivered'],
            OrderStatus::activeValues(),
        );
    }

    public function test_remaining_quantity_is_not_a_stored_column(): void
    {
        $item = $this->orderWithItem()->items()->firstOrFail();

        $this->assertArrayNotHasKey('remaining_quantity', $item->getAttributes());
        $this->assertArrayNotHasKey('remaining_quantity', OrderItem::query()->firstOrFail()->getAttributes());
    }
}
