<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_color_is_required_independently_from_choice_toggle(): void
    {
        $product = Product::factory()->create(['color_enabled' => false]);

        $this->expectException(ValidationException::class);
        $product->variants()->create(['color' => null, 'size' => '40', 'available_quantity' => 5]);
    }

    public function test_sizes_may_be_assigned_when_size_choice_is_disabled(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '40',
            'available_quantity' => 5,
        ]);

        $this->assertSame('40', $variant->size);
    }

    public function test_variant_combination_remains_unique(): void
    {
        $product = Product::factory()->create();
        $product->variants()->create(['color' => 'Black', 'size' => '40', 'available_quantity' => 5]);

        $this->expectException(QueryException::class);
        $product->variants()->create(['color' => 'Black', 'size' => '40', 'available_quantity' => 9]);
    }

    public function test_validation_rejects_negative_quantity(): void
    {
        $product = Product::factory()->create();

        $this->expectException(ValidationException::class);
        ProductVariant::validate(['color' => 'Black', 'size' => '40', 'available_quantity' => -1], $product);
    }
}
