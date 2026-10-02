<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OptionalSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_size_choice_defaults_off_but_assigned_sizes_remain_visible(): void
    {
        $product = Product::factory()->create(['color_enabled' => true, 'size_enabled' => false]);
        $product->variants()->createMany([
            ['color' => 'Black', 'size' => '40', 'available_quantity' => 5],
            ['color' => 'Black', 'size' => '41', 'available_quantity' => 5],
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.size_enabled', false)
                ->where('variants.0.color', 'Black')
                ->where('variants.0.sizes.0.size', '40')
                ->where('variants.0.sizes.1.size', '41'));
    }

    public function test_enabled_size_choice_accepts_one_common_size_for_multiple_colors(): void
    {
        $product = Product::factory()->create(['color_enabled' => true, 'size_enabled' => true]);
        $black = $product->variants()->create(['color' => 'Black', 'size' => '40', 'available_quantity' => 5]);
        $white = $product->variants()->create(['color' => 'White', 'size' => '40', 'available_quantity' => 5]);

        $this->post('/cart/add', [
            'variant_ids' => [$black->id, $white->id],
            'quantity' => 7,
        ])->assertSessionHasNoErrors();

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.color', 'Black، White')
            ->where('items.0.size', '40')
            ->where('items.0.quantity', 7)
            ->where('items.0.color_count', 2)
            ->where('items.0.pieces_quantity', 14)
            ->where('total_quantity', 14));
    }
}
