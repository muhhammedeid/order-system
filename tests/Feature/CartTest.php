<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_to_cart_persists_and_survives_navigation(): void
    {
        $variant = ProductVariant::factory()->create(['available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertRedirect('/cart');

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')
            ->has('items', 1)
            ->where('items.0.variant_id', $variant->id)
            ->where('items.0.quantity', 3)
            ->where('total_quantity', 3));

        $this->get('/catalog');
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.quantity', 3));
    }

    public function test_quantity_must_be_positive(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->from('/cart')
            ->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 0])
            ->assertSessionHasErrors('quantity');

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => -2])
            ->assertSessionHasErrors('quantity');

        $this->assertSame([], Cart::items());
    }

    public function test_quantity_cannot_exceed_available(): void
    {
        $variant = ProductVariant::factory()->create(['available_quantity' => 5]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 11])
            ->assertSessionHasErrors('quantity');

        $this->assertSame([], Cart::items());
    }

    public function test_inactive_product_cannot_be_added(): void
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->inactive())
            ->create();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertSessionHasErrors('variant_id');

        $this->assertSame([], Cart::items());
    }

    public function test_adding_same_variant_increments_quantity(): void
    {
        $variant = ProductVariant::factory()->create(['available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);

        $this->assertSame(5, Cart::count());
        $this->assertSame(1, count(Cart::items()));
    }

    public function test_update_quantity_enforces_availability(): void
    {
        $variant = ProductVariant::factory()->create(['available_quantity' => 5]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->post('/cart/update', ['variant_id' => $variant->id, 'quantity' => 4]);
        $this->assertSame(4, Cart::count());

        $this->post('/cart/update', ['variant_id' => $variant->id, 'quantity' => 6])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(4, Cart::count());
    }

    public function test_remove_and_clear_work(): void
    {
        $variantA = ProductVariant::factory()->create();
        $variantB = ProductVariant::factory()->create();

        $this->post('/cart/add', ['variant_id' => $variantA->id, 'quantity' => 1]);
        $this->post('/cart/add', ['variant_id' => $variantB->id, 'quantity' => 1]);

        $this->post('/cart/remove', ['variant_id' => $variantA->id]);
        $this->assertSame(1, count(Cart::items()));

        $this->post('/cart/clear');
        $this->assertSame([], Cart::items());
    }

    public function test_request_price_products_never_expose_price_in_cart(): void
    {
        $public = ProductVariant::factory()
            ->for(Product::factory()->create(['price_visibility' => 'public', 'price' => 450]))
            ->create(['available_quantity' => 5]);

        $secret = ProductVariant::factory()
            ->for(Product::factory()->requestPrice()->create(['name' => 'Secret Cart Shoe', 'price' => 1234.56]))
            ->create(['available_quantity' => 5]);

        $this->post('/cart/add', ['variant_id' => $public->id, 'quantity' => 2]);
        $this->post('/cart/add', ['variant_id' => $secret->id, 'quantity' => 1]);

        $response = $this->get('/cart');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items.0.unit_price', '450.00')
            ->where('items.0.line_total', '900.00')
            ->where('items.1.unit_price', null)
            ->where('items.1.line_total', null)
            ->where('total_price', '900.00'));

        $this->assertStringNotContainsString('1234.56', $response->getContent());
    }

    public function test_stale_cart_items_are_dropped_from_display(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);

        $variant->delete();

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->has('items', 0));
    }
}
