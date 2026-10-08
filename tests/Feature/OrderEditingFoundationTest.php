<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEditingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function submittedOrder(int $quantity = 3): Order
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['size_enabled' => true]))
            ->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 0]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => $quantity]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        return Order::query()->latest('id')->firstOrFail();
    }

    public function test_new_and_confirmed_orders_are_editable(): void
    {
        $new = $this->submittedOrder();
        $this->assertTrue($new->isEditable());

        $confirmed = $this->submittedOrder();
        $confirmed->confirm();
        $this->assertTrue($confirmed->refresh()->isEditable());

        $cancelled = $this->submittedOrder();
        $cancelled->cancel();
        $this->assertFalse($cancelled->refresh()->isEditable());
    }

    public function test_total_quantity_recalculates_after_an_item_quantity_edit(): void
    {
        $order = $this->submittedOrder(3);
        $item = $order->items()->firstOrFail();

        $item->requested_quantity = 7;
        $item->save();

        $order->recalculateTotalQuantity();

        $this->assertSame(7, $order->refresh()->total_quantity);
        $this->assertSame(7, $item->refresh()->quantity);
    }

    public function test_total_quantity_recalculates_after_adding_and_removing_items(): void
    {
        $order = $this->submittedOrder(2);

        $product = Product::factory()->create(['size_enabled' => true]);
        $variant = $product->variants()->create([
            'color' => 'White',
            'size' => '42',
            'available_quantity' => 0,
        ]);

        $extra = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_code' => $product->product_code,
            'product_name' => $product->name,
            'color' => $variant->color,
            'size' => $variant->size,
            'quantity' => 4,
            'unit_price' => 100,
            'price_visibility' => 'public',
        ]);

        $order->recalculateTotalQuantity();
        $this->assertSame(6, $order->refresh()->total_quantity);

        $extra->delete();
        $order->recalculateTotalQuantity();

        $this->assertSame(2, $order->refresh()->total_quantity);
    }

    public function test_pending_edits_keep_delivered_quantity_and_snapshots_intact(): void
    {
        $order = $this->submittedOrder(3);
        $item = $order->items()->firstOrFail();

        $originalSnapshot = [
            'product_code' => $item->product_code,
            'product_name' => $item->product_name,
            'color' => $item->color,
            'size' => $item->size,
        ];

        $item->requested_quantity = 5;
        $item->save();
        $order->recalculateTotalQuantity();

        $item->refresh();

        $this->assertSame(0, $item->delivered_quantity);
        $this->assertSame(5, $item->remaining_quantity);
        $this->assertSame($originalSnapshot, [
            'product_code' => $item->product_code,
            'product_name' => $item->product_name,
            'color' => $item->color,
            'size' => $item->size,
        ]);
    }
}
