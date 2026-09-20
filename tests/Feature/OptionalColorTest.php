<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class OptionalColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_products_default_to_color_enabled(): void
    {
        $product = Product::factory()->create();

        $this->assertTrue($product->color_enabled);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'color_enabled' => true,
        ]);
    }

    public function test_existing_products_keep_color_enabled_after_migration(): void
    {
        $id = DB::table('products')->insertGetId([
            'product_code' => 'LEG-C1',
            'name' => 'Legacy Color Shoe',
            'slug' => 'legacy-color-shoe',
            'price_visibility' => 'public',
            'price' => 100,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('products', ['id' => $id, 'color_enabled' => true]);
        $this->assertTrue(Product::query()->findOrFail($id)->color_enabled);
    }

    public function test_admin_create_form_defaults_color_selection_on(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_code' => 'CL-100',
                'name' => 'Default Color Shoe',
                'slug' => 'default-color-shoe',
                'price_visibility' => 'public',
                'price' => 100,
                'active' => true,
                'images' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'product_code' => 'CL-100',
            'color_enabled' => true,
        ]);
    }

    public function test_admin_form_shows_a_friendly_error_when_disabling_color_with_colored_variants(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::factory()->create(['color_enabled' => true]);
        $product->variants()->create(['color' => 'Black', 'size' => null, 'available_quantity' => 5]);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['color_enabled' => false])
            ->call('save')
            ->assertHasFormErrors(['color_enabled'])
            ->assertSee('لا يمكن تعطيل اختيار اللون');

        $this->assertTrue($product->refresh()->color_enabled);
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame('Black', $product->variants()->first()->color);
    }

    public function test_disabling_color_is_blocked_while_colored_variants_exist(): void
    {
        $product = Product::factory()->create(['color_enabled' => true]);
        $product->variants()->create(['color' => 'Black', 'size' => null, 'available_quantity' => 5]);

        try {
            $product->update(['color_enabled' => false]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('color_enabled', $exception->errors());
        }

        $this->assertTrue($product->refresh()->color_enabled);
    }

    public function test_direct_model_assignment_cannot_disable_color_while_colored_variants_exist(): void
    {
        $product = Product::factory()->create(['color_enabled' => true]);
        $product->variants()->create(['color' => 'Black', 'size' => null, 'available_quantity' => 5]);

        $this->expectException(ValidationException::class);

        $product->forceFill(['color_enabled' => false])->save();
    }

    public function test_enabling_color_is_blocked_while_uncolored_variants_exist(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => true]);
        $product->variants()->create(['color' => null, 'size' => '40', 'available_quantity' => 5]);

        try {
            $product->update(['color_enabled' => true]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('color_enabled', $exception->errors());
        }

        $this->assertFalse($product->refresh()->color_enabled);
    }

    public function test_disabling_color_is_allowed_without_colored_variants(): void
    {
        $product = Product::factory()->create(['color_enabled' => true]);

        $product->update(['color_enabled' => false]);

        $this->assertFalse($product->refresh()->color_enabled);
    }

    public function test_color_disabled_size_enabled_product_groups_by_size_only(): void
    {
        VariantSize::create(['name' => '40', 'sort_order' => 0, 'active' => true]);
        VariantSize::create(['name' => '41', 'sort_order' => 1, 'active' => true]);

        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 300,
            'color_enabled' => false,
            'size_enabled' => true,
        ]);

        $product->variants()->create([
            'color' => null,
            'size' => '40',
            'available_quantity' => 5,
        ]);

        $variant = $product->variants()->create([
            'color' => null,
            'size' => '41',
            'available_quantity' => 5,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.color_enabled', false)
                ->where('product.size_enabled', true)
                ->has('variants', 1)
                ->where('variants.0.color', null)
                ->has('variants.0.sizes', 2)
                ->where('variants.0.sizes.0.size', '40')
                ->where('variants.0.sizes.1.size', '41'));

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->firstOrFail();

        $this->assertNull($item->color);
        $this->assertSame('41', $item->size);
    }

    public function test_color_and_size_disabled_product_can_be_ordered_without_fake_values(): void
    {
        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 300,
            'color_enabled' => false,
            'size_enabled' => false,
        ]);

        $variant = $product->variants()->create([
            'color' => null,
            'size' => null,
            'available_quantity' => 5,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.color_enabled', false)
                ->where('product.size_enabled', false)
                ->has('variants', 1)
                ->where('variants.0.color', null)
                ->where('variants.0.sizes.0.size', null));

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.color', null)
            ->where('items.0.size', null));

        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->firstOrFail();

        $this->assertNull($item->color);
        $this->assertNull($item->size);
        $this->assertDatabaseMissing('product_variants', ['color' => 'N/A']);
        $this->assertDatabaseMissing('product_variants', ['size' => 'N/A']);
    }

    public function test_color_enabled_size_disabled_product_remains_color_driven_end_to_end(): void
    {
        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 300,
            'color_enabled' => true,
            'size_enabled' => false,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.color_enabled', true)
                ->where('product.size_enabled', false)
                ->where('variants.0.color', 'Black')
                ->where('variants.0.sizes.0.size', null));

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->firstOrFail();

        $this->assertSame('Black', $item->color);
        $this->assertNull($item->size);
    }

    public function test_color_and_size_enabled_product_remains_driven_by_both_dimensions(): void
    {
        VariantSize::create(['name' => '40', 'sort_order' => 0, 'active' => true]);

        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 300,
            'color_enabled' => true,
            'size_enabled' => true,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '40',
            'available_quantity' => 5,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.color_enabled', true)
                ->where('product.size_enabled', true)
                ->where('variants.0.color', 'Black')
                ->where('variants.0.sizes.0.size', '40'));

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $item = OrderItem::query()->firstOrFail();

        $this->assertSame('Black', $item->color);
        $this->assertSame('40', $item->size);
    }

    public function test_color_enabled_product_hides_raw_uncolored_variants_from_the_storefront(): void
    {
        $product = Product::factory()->create(['color_enabled' => true]);

        // Raw insert bypasses the domain guard on purpose: it simulates legacy
        // data the storefront must not expose or offer for selection.
        DB::table('product_variants')->insert([
            'product_id' => $product->getKey(),
            'color' => null,
            'size' => null,
            'available_quantity' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.color_enabled', true)
                ->has('variants', 0));
    }

    public function test_color_key_is_hidden_from_serialized_variants(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertArrayNotHasKey('color_key', $variant->toArray());
        $this->assertArrayNotHasKey('color_key', json_decode($variant->fresh()->toJson(), true));
    }
}
