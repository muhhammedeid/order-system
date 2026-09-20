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

    private const SIZES = ['37', '38', '39', '40', '41'];

    private function addColor(Product $product, string $color): array
    {
        return collect(self::SIZES)->map(fn (string $size) => $product->variants()->create([
            'color' => $color,
            'size' => $size,
            'available_quantity' => 10,
        ]))->all();
    }

    public function test_disabling_color_choice_keeps_available_colors_visible(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);
        $black = $this->addColor($product, 'Black')[0];
        $this->addColor($product, 'White');

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
            ->where('items.0.color_count', 2)
            ->where('items.0.pieces_quantity', 20)
            ->where('total_quantity', 20)
            ->where('items.0.color', 'Black، White')
            ->where('items.0.size', '37، 38، 39، 40، 41'));

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $this->assertSame(20, Order::query()->firstOrFail()->total_quantity);
        $item = OrderItem::query()->firstOrFail();
        $this->assertSame(10, $item->requested_quantity);
        $this->assertSame(2, $item->color_count);
        $this->assertSame(20, $item->quantity);
        $this->assertSame('Black، White', $item->color);
    }

    public function test_size_choice_cannot_be_enabled_when_color_choice_is_disabled(): void
    {
        $this->expectException(ValidationException::class);

        Product::factory()->create(['color_enabled' => false, 'size_enabled' => true]);
    }

    public function test_multiple_selected_colors_multiply_quantity_and_price_by_color_count(): void
    {
        $product = Product::factory()->create([
            'color_enabled' => true,
            'size_enabled' => false,
            'price_visibility' => 'public',
            'price' => 850,
        ]);
        $black37 = $this->addColor($product, 'Black')[0];
        $white37 = $this->addColor($product, 'White')[0];

        $this->post('/cart/add', [
            'variant_ids' => [$black37->id, $white37->id],
            'quantity' => 5,
        ])->assertSessionHasNoErrors();

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.quantity', 5)
            ->where('items.0.color_count', 2)
            ->where('items.0.pieces_quantity', 10)
            ->where('items.0.line_total', '8500.00')
            ->where('total_quantity', 10)
            ->where('total_price', '8500.00')
            ->where('items.0.color', 'Black، White')
            ->where('items.0.size', '37، 38، 39، 40، 41'));
    }

    public function test_quantity_must_divide_evenly_across_sizes_when_size_choice_is_disabled(): void
    {
        $product = Product::factory()->create(['color_enabled' => true, 'size_enabled' => false]);
        $black37 = $this->addColor($product, 'Black')[0];

        $this->post('/cart/add', [
            'variant_ids' => [$black37->id],
            'quantity' => 6,
        ])->assertSessionHasErrors('quantity');

        $this->post('/cart/add', [
            'variant_ids' => [$black37->id],
            'quantity' => 5,
        ])->assertSessionHasNoErrors();
    }
}
