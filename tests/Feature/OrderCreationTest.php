<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private function publicVariant(array $productAttributes = [], array $variantAttributes = []): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->create([
                'price_visibility' => 'public',
                'price' => 450,
                ...$productAttributes,
            ]))
            ->create(['available_quantity' => 10, ...$variantAttributes]);
    }

    public function test_order_is_created_with_snapshots_and_correct_customer(): void
    {
        $variant = $this->publicVariant();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);

        $response = $this->post('/checkout', [
            'name' => 'Test Store',
            'phone' => '01001234567',
            'city' => 'Nasr City',
            'customer_notes' => 'Deliver after Friday',
        ]);

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $response->assertRedirect("/order/success/{$order->order_number}");

        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{5}$/', $order->order_number);
        $this->assertSame('new', $order->status->value);
        $this->assertSame(3, $order->total_quantity);
        $this->assertSame('Deliver after Friday', $order->customer_notes);

        $this->assertDatabaseHas('customers', [
            'id' => $order->customer_id,
            'phone' => '01001234567',
        ]);

        $item = OrderItem::query()->first();

        $this->assertSame($variant->product->product_code, $item->product_code);
        $this->assertSame($variant->product->name, $item->product_name);
        $this->assertSame($variant->color, $item->color);
        $this->assertSame($variant->size, $item->size);
        $this->assertSame(3, $item->quantity);
        $this->assertSame('450.00', $item->unit_price);
        $this->assertSame('public', $item->price_visibility);
    }

    public function test_empty_cart_is_rejected(): void
    {
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567'])
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_submit_time_quantity_is_revalidated(): void
    {
        $variant = $this->publicVariant();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);

        // simulate another concurrent order consuming stock after add-to-cart
        $variant->update(['available_quantity' => 2]);

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567'])
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_inactive_product_is_rejected_at_submit_time(): void
    {
        $variant = $this->publicVariant();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2]);

        $variant->product->update(['active' => false]);

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567'])
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_request_price_item_has_null_unit_price_and_snapshot(): void
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->requestPrice()->create(['price' => 555.55]))
            ->create(['available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->first();

        $this->assertNull($item->unit_price);
        $this->assertSame('request_price', $item->price_visibility);
        $this->assertStringNotContainsString('555.55', $this->get('/order/success/'.$item->order->order_number)->getContent());
    }

    public function test_total_quantity_sums_all_items(): void
    {
        $variantA = $this->publicVariant();
        $variantB = $this->publicVariant();

        $this->post('/cart/add', ['variant_id' => $variantA->id, 'quantity' => 2]);
        $this->post('/cart/add', ['variant_id' => $variantB->id, 'quantity' => 5]);

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $this->assertSame(7, Order::query()->first()->total_quantity);
    }

    public function test_cart_is_cleared_after_successful_submit(): void
    {
        $variant = $this->publicVariant();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $this->get('/cart')->assertInertia(fn ($page) => $page->has('items', 0));
    }

    public function test_available_quantity_is_not_decremented_by_order_submission(): void
    {
        $variant = $this->publicVariant();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 4]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $this->assertSame(10, $variant->refresh()->available_quantity);
    }

    public function test_order_numbers_are_sequential_and_unique(): void
    {
        $variant = $this->publicVariant();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);
            $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);
        }

        $numbers = Order::query()->orderBy('id')->pluck('order_number')->all();

        $this->assertCount(3, $numbers);
        $this->assertCount(3, array_unique($numbers));

        $years = array_map(fn ($number) => (int) explode('-', $number)[1], $numbers);
        $sequences = array_map(fn ($number) => (int) substr($number, -5), $numbers);

        $this->assertSame([1, 2, 3], $sequences);
        $this->assertGreaterThan(2000, min($years));
    }

    public function test_deleting_customer_is_blocked_when_orders_exist(): void
    {
        $variant = $this->publicVariant();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $order = Order::query()->first();
        $customer = $order->customer;

        $this->expectException(\Illuminate\Database\QueryException::class);

        $customer->delete();
    }

    public function test_order_survives_product_deletion(): void
    {
        $variant = $this->publicVariant();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->first();
        $item->product->delete();
        $item->refresh();

        $this->assertNull($item->product_id);
        $this->assertNotNull($item->product_code);
        $this->assertNotNull($item->product_name);
    }

    public function test_success_page_shows_minimal_order_data(): void
    {
        $variant = $this->publicVariant();
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $order = Order::query()->first();

        $this->get("/order/success/{$order->order_number}")
            ->assertInertia(fn ($page) => $page
                ->component('Order/Success')
                ->where('order_number', $order->order_number)
                ->missing('items')
                ->missing('customer'));
    }

    public function test_success_page_rejects_unknown_order_numbers(): void
    {
        $this->get('/order/success/ORD-0000-99999')->assertNotFound();
    }
}
