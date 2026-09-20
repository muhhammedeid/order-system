<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_page_renders_active_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'Details Shoe',
            'price_visibility' => 'public',
            'price' => 450,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Show')
                ->where('product.name', 'Details Shoe')
                ->where('product.product_code', $product->product_code)
                ->where('product.price', '450.00')
                ->where('product.price_visibility', 'public')
                ->where('product.color_enabled', true)
                ->where('product.size_enabled', false));
    }

    public function test_inactive_product_returns_404(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->get("/product/{$product->slug}")->assertNotFound();
    }

    public function test_gallery_images_are_in_sort_order(): void
    {
        $product = Product::factory()->create();

        $product->images()->createMany([
            ['image_path' => 'products/images/second.png', 'sort_order' => 2],
            ['image_path' => 'products/images/first.png', 'sort_order' => 1],
            ['image_path' => 'products/images/third.png', 'sort_order' => 3],
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.images', [
                    url('/storage/products/images/first.png'),
                    url('/storage/products/images/second.png'),
                    url('/storage/products/images/third.png'),
                ]));
    }

    public function test_request_price_product_never_exposes_its_price(): void
    {
        $product = Product::factory()->requestPrice()->create([
            'name' => 'Secret Detail Shoe',
            'price' => 888.99,
        ]);

        $response = $this->get("/product/{$product->slug}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('product.price', null)
            ->where('product.price_visibility', 'request_price'));

        $this->assertStringNotContainsString('888.99', $response->getContent());
        $this->assertSame('888.99', $product->refresh()->price);
    }

    public function test_sized_variants_are_grouped_by_color_without_exposing_stock(): void
    {
        VariantSize::create(['name' => '40', 'sort_order' => 1, 'active' => true]);
        VariantSize::create(['name' => '41', 'sort_order' => 2, 'active' => true]);

        $product = Product::factory()->create(['size_enabled' => true]);

        $product->variants()->createMany([
            ['color' => 'Black', 'size' => '41', 'available_quantity' => 10],
            ['color' => 'Black', 'size' => '40', 'available_quantity' => 0],
            ['color' => 'White', 'size' => '40', 'available_quantity' => 8],
        ]);

        $response = $this->get("/product/{$product->slug}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('product.color_enabled', true)
            ->where('product.size_enabled', true)
            ->has('variants', 2)
            ->where('variants.0.color', 'Black')
            ->where('variants.0.sizes.0.size', '40')
            ->where('variants.0.sizes.1.size', '41')
            ->where('variants.1.color', 'White')
            ->where('variants.1.sizes.0.size', '40')
            ->missing('variants.0.sizes.0.available_quantity'));

        $this->assertStringNotContainsString('available_quantity', $response->getContent());
    }

    public function test_unsized_product_exposes_color_only_variants(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $product->variants()->createMany([
            ['color' => 'Black', 'size' => null, 'available_quantity' => 4],
            ['color' => 'White', 'size' => null, 'available_quantity' => 0],
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.color_enabled', true)
                ->where('product.size_enabled', false)
                ->has('variants', 2)
                ->where('variants.0.sizes.0.size', null)
                ->has('variants.0.sizes', 1)
                ->has('variants.1.sizes', 1));
    }

    public function test_lookup_order_is_used_even_when_lookups_are_inactive(): void
    {
        VariantColor::create(['name' => 'White', 'sort_order' => 1, 'active' => false]);
        VariantColor::create(['name' => 'Black', 'sort_order' => 2, 'active' => false]);
        VariantSize::create(['name' => '42', 'sort_order' => 1, 'active' => false]);
        VariantSize::create(['name' => '40', 'sort_order' => 2, 'active' => false]);

        $product = Product::factory()->create(['size_enabled' => true]);

        $product->variants()->createMany([
            ['color' => 'Black', 'size' => '40', 'available_quantity' => 5],
            ['color' => 'White', 'size' => '42', 'available_quantity' => 3],
        ]);

        $this->get("/product/{$product->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('variants', 2)
                ->where('variants.0.color', 'White')
                ->where('variants.0.sizes.0.size', '42')
                ->where('variants.1.color', 'Black')
                ->where('variants.1.sizes.0.size', '40'));
    }
}
