<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemColorQuantity;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    private function firstColorRow(Order $order): OrderItemColorQuantity
    {
        return $order->items()->firstOrFail()->colorQuantities()->firstOrFail();
    }

    public function test_checkout_creates_one_color_row_per_ordered_color_with_zero_delivered(): void
    {
        $order = $this->orderWithItem(5);
        $item = $order->items()->firstOrFail();

        $this->assertSame(['Black'], $item->colorQuantities->pluck('color')->all());
        $this->assertSame(5, $this->firstColorRow($order)->requested_quantity);
        $this->assertSame(0, $this->firstColorRow($order)->delivered_quantity);
        $this->assertSame(0, $item->delivered_quantity);
        $this->assertSame(5, $item->remaining_quantity);
    }

    public function test_multi_color_checkout_creates_a_row_per_color_with_the_requested_quantity(): void
    {
        $product = Product::factory()->create([
            'size_enabled' => false,
            'color_enabled' => true,
        ]);

        $variants = collect(['Black', 'White'])
            ->map(fn (string $color): ProductVariant => $product->variants()->create([
                'color' => $color,
                'size' => '37',
                'available_quantity' => 0,
            ]));

        $this->post('/cart/add', [
            'variant_ids' => $variants->pluck('id')->all(),
            'quantity' => 5,
        ]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->firstOrFail();

        $this->assertSame(['Black', 'White'], $item->colorQuantities->pluck('color')->all());
        $this->assertSame([5, 5], $item->colorQuantities->pluck('requested_quantity')->all());
        $this->assertSame([0, 0], $item->colorQuantities->pluck('delivered_quantity')->all());
        $this->assertSame(10, $item->quantity);
        $this->assertSame(0, $item->delivered_quantity);
    }

    public function test_color_delivered_quantity_cannot_be_negative(): void
    {
        $colorRow = $this->firstColorRow($this->orderWithItem());

        $this->expectException(\InvalidArgumentException::class);

        $colorRow->delivered_quantity = -1;
        $colorRow->save();
    }

    public function test_color_delivered_quantity_cannot_exceed_the_requested_quantity(): void
    {
        $colorRow = $this->firstColorRow($this->orderWithItem(4));

        $this->expectException(\InvalidArgumentException::class);

        $colorRow->delivered_quantity = 5;
        $colorRow->save();
    }

    public function test_set_delivered_quantity_updates_remaining_and_allows_boundaries(): void
    {
        $colorRow = $this->firstColorRow($this->orderWithItem(4));

        $colorRow->setDeliveredQuantity(0);
        $this->assertSame(4, $colorRow->refresh()->remaining_quantity);

        $colorRow->setDeliveredQuantity(2);
        $this->assertSame(2, $colorRow->remaining_quantity);

        $colorRow->setDeliveredQuantity(4);
        $this->assertSame(0, $colorRow->remaining_quantity);
    }

    public function test_set_delivered_quantity_rejects_out_of_range_values(): void
    {
        $colorRow = $this->firstColorRow($this->orderWithItem(4));

        try {
            $colorRow->setDeliveredQuantity(-1);
            $this->fail('Expected InvalidArgumentException for a negative value');
        } catch (\InvalidArgumentException) {
        }

        try {
            $colorRow->setDeliveredQuantity(5);
            $this->fail('Expected InvalidArgumentException for an over-delivery');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, $colorRow->refresh()->delivered_quantity);
    }

    public function test_requested_quantity_must_be_positive(): void
    {
        $colorRow = $this->firstColorRow($this->orderWithItem());

        $this->expectException(ValidationException::class);

        $colorRow->requested_quantity = 0;
        $colorRow->save();
    }

    public function test_synchronized_aggregate_rejects_more_delivered_pieces_than_ordered(): void
    {
        $order = $this->orderWithItem(4);
        $item = $order->items()->firstOrFail();
        $colorRow = $this->firstColorRow($order);

        $colorRow->setDeliveredQuantity(4);
        $item->refresh()->syncDeliveredAggregate();
        $this->assertSame(4, $item->refresh()->delivered_quantity);

        // A legacy unallocated remainder beyond the ordered quantity is invalid.
        $item->forceFill(['unallocated_delivered_quantity' => 1])->save();

        $this->expectException(\InvalidArgumentException::class);

        $item->refresh()->syncDeliveredAggregate();
    }

    public function test_synchronized_aggregate_includes_legacy_unallocated_deliveries(): void
    {
        $order = $this->orderWithItem(5);
        $item = $order->items()->firstOrFail();

        $this->firstColorRow($order)->setDeliveredQuantity(2);
        $item->refresh()->forceFill(['unallocated_delivered_quantity' => 1])->save();
        $item->refresh()->syncDeliveredAggregate();

        $this->assertSame(3, $item->refresh()->delivered_quantity);
        $this->assertSame(2, $item->remaining_quantity);
    }

    public function test_delivery_status_is_null_when_nothing_is_delivered(): void
    {
        $order = $this->orderWithItem();

        $this->assertNull($order->deriveDeliveryStatus());
    }

    public function test_delivery_status_is_partially_delivered_when_some_quantity_remains(): void
    {
        $order = $this->orderWithItem(5);

        $this->firstColorRow($order)->setDeliveredQuantity(2);

        $this->assertSame(OrderStatus::PartiallyDelivered, $order->deriveDeliveryStatus());
    }

    public function test_delivery_status_is_delivered_when_all_items_are_complete(): void
    {
        $order = $this->orderWithItem(5);

        $this->firstColorRow($order)->setDeliveredQuantity(5);

        $this->assertSame(OrderStatus::Delivered, $order->deriveDeliveryStatus());
    }

    public function test_delivery_status_requires_all_items_to_be_complete(): void
    {
        $order = $this->orderWithItem(2);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['size_enabled' => true]))
            ->create(['color' => 'White', 'size' => '41', 'available_quantity' => 0]);

        $extra = $order->items()->create([
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
        $items[0]->colorQuantities()->firstOrFail()->setDeliveredQuantity(2);

        $this->assertSame(OrderStatus::PartiallyDelivered, $order->refresh()->deriveDeliveryStatus());

        $extra->colorQuantities()->firstOrFail()->setDeliveredQuantity(3);

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->deriveDeliveryStatus());
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
        $colorRow = $this->firstColorRow($item->order);

        $this->assertArrayNotHasKey('remaining_quantity', $item->getAttributes());
        $this->assertArrayNotHasKey('remaining_quantity', OrderItem::query()->firstOrFail()->getAttributes());
        $this->assertArrayNotHasKey('remaining_quantity', $colorRow->getAttributes());
    }
}
