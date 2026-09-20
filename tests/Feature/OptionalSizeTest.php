<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class OptionalSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_products_default_to_size_disabled(): void
    {
        $product = Product::factory()->create();

        $this->assertFalse($product->size_enabled);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'size_enabled' => false,
        ]);
    }

    public function test_admin_create_form_defaults_size_selection_off(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_code' => 'SZ-100',
                'name' => 'Default Size Shoe',
                'slug' => 'default-size-shoe',
                'price_visibility' => 'public',
                'price' => 100,
                'active' => true,
                'images' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'product_code' => 'SZ-100',
            'size_enabled' => false,
        ]);
    }

    public function test_unsized_product_can_be_ordered_without_fake_size_values(): void
    {
        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 300,
            'size_enabled' => false,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.size_enabled', false)
                ->where('variants.0.sizes.0.size', null));

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.size', null));

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->firstOrFail();

        $this->assertNull($item->size);
        $this->assertDatabaseMissing('product_variants', ['size' => 'N/A']);
    }

    public function test_sized_product_remains_size_driven_end_to_end(): void
    {
        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 300,
            'size_enabled' => true,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '42',
            'available_quantity' => 5,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.size_enabled', true)
                ->where('variants.0.sizes.0.size', '42'));

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $this->assertSame('42', OrderItem::query()->firstOrFail()->size);
    }

    public function test_enabled_product_hides_raw_unsized_variants_from_the_storefront(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        // Raw insert bypasses the domain guard on purpose: it simulates legacy
        // data the storefront must not expose or offer for selection.
        DB::table('product_variants')->insert([
            'product_id' => $product->getKey(),
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.size_enabled', true)
                ->has('variants', 0));
    }

    public function test_size_key_is_hidden_from_serialized_variants(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertArrayNotHasKey('size_key', $variant->toArray());
        $this->assertArrayNotHasKey('size_key', json_decode($variant->fresh()->toJson(), true));
    }
}
