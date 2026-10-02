<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductsAndCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_code_is_required(): void
    {
        $this->expectException(ValidationException::class);

        Product::validate([
            'product_code' => '',
            'name' => 'Test Shoe',
            'slug' => 'shoe-a',
            'price_visibility' => 'public',
            'price' => 100,
        ]);
    }

    public function test_product_code_must_be_unique(): void
    {
        Product::factory()->create(['product_code' => 'SH-100']);

        $this->expectException(ValidationException::class);

        Product::validate([
            'product_code' => 'SH-100',
            'name' => 'Test Shoe',
            'slug' => 'other-slug',
            'price_visibility' => 'public',
            'price' => 100,
        ]);
    }

    public function test_product_slug_must_be_unique(): void
    {
        Product::factory()->create(['slug' => 'running-shoe']);

        $this->expectException(ValidationException::class);

        Product::validate([
            'product_code' => 'SH-200',
            'name' => 'Test Shoe',
            'slug' => 'running-shoe',
            'price_visibility' => 'public',
            'price' => 100,
        ]);
    }

    public function test_category_slug_must_be_unique(): void
    {
        Category::factory()->create(['slug' => 'men-shoes']);

        $this->expectException(QueryException::class);

        Category::query()->create(['name' => 'Other', 'slug' => 'men-shoes', 'active' => true]);
    }

    public function test_public_product_requires_a_price(): void
    {
        $this->expectException(ValidationException::class);

        Product::validate([
            'product_code' => 'SH-300',
            'name' => 'Test Shoe',
            'slug' => 'shoe-c',
            'price_visibility' => 'public',
            'price' => null,
        ]);
    }

    public function test_negative_price_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Product::validate([
            'product_code' => 'SH-400',
            'name' => 'Test Shoe',
            'slug' => 'shoe-d',
            'price_visibility' => 'public',
            'price' => -5,
        ]);
    }

    public function test_request_price_product_may_have_no_price(): void
    {
        $product = Product::factory()->requestPrice()->create();

        $this->assertSame('request_price', $product->price_visibility->value);
        $this->assertNull($product->price);
    }

    public function test_active_state_persists(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->assertFalse($product->active);

        $product->update(['active' => true]);
        $product->refresh();

        $this->assertTrue($product->active);
    }

    public function test_product_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();

        $this->assertTrue($product->category->is($category));
        $this->assertTrue($category->products->first()->is($product));
    }

    public function test_category_image_path_can_be_attached(): void
    {
        $category = Category::factory()->create([
            'image_path' => 'categories/images/women-shoes.webp',
        ]);

        $this->assertSame('categories/images/women-shoes.webp', $category->refresh()->image_path);
    }

    public function test_product_images_persist_and_keep_sort_order(): void
    {
        $product = Product::factory()->create();

        $product->images()->createMany([
            ['image_path' => 'products/images/c.png', 'sort_order' => 3],
            ['image_path' => 'products/images/a.png', 'sort_order' => 1],
            ['image_path' => 'products/images/b.png', 'sort_order' => 2],
        ]);

        $product->refresh();

        $this->assertSame(3, $product->images->count());
        $this->assertSame(
            ['a.png', 'b.png', 'c.png'],
            $product->images->map(fn (ProductImage $image) => basename($image->image_path))->all(),
        );
    }
}
