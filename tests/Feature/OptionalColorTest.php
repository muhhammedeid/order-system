<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OptionalColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_color_choice_keeps_available_colors_visible(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);
        $black = $product->variants()->create(['color' => 'Black', 'size' => '40', 'available_quantity' => 10]);
        $product->variants()->create(['color' => 'White', 'size' => '41', 'available_quantity' => 10]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.color_enabled', false)
                ->has('variants', 2)
                ->where('variants.0.color', 'Black')
                ->where('variants.1.color', 'White'));

        $this->post('/cart/add', ['variant_id' => $black->id, 'quantity' => 10]);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.quantity', 10)
            ->where('total_quantity', 10)
            ->where('items.0.color', 'Black، White')
            ->where('items.0.size', '40، 41'));

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $this->assertSame(10, Order::query()->firstOrFail()->total_quantity);
        $this->assertSame('Black، White', OrderItem::query()->firstOrFail()->color);
    }

    public function test_size_choice_cannot_be_enabled_when_color_choice_is_disabled(): void
    {
        $this->expectException(ValidationException::class);

        Product::factory()->create(['color_enabled' => false, 'size_enabled' => true]);
    }

    public function test_multiple_selected_colors_keep_one_global_quantity(): void
    {
        $product = Product::factory()->create(['color_enabled' => true, 'size_enabled' => false]);
        $black40 = $product->variants()->create(['color' => 'Black', 'size' => '40', 'available_quantity' => 10]);
        $product->variants()->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 10]);
        $white40 = $product->variants()->create(['color' => 'White', 'size' => '40', 'available_quantity' => 10]);
        $product->variants()->create(['color' => 'White', 'size' => '41', 'available_quantity' => 10]);

        $this->post('/cart/add', [
            'variant_ids' => [$black40->id, $white40->id],
            'quantity' => 10,
        ])->assertSessionHasNoErrors();

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.quantity', 10)
            ->where('total_quantity', 10)
            ->where('items.0.color', 'Black، White')
            ->where('items.0.size', '40، 41'));
    }
}

