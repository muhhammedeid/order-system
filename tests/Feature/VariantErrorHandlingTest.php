<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_friendly_duplicate_lookup_covers_color_and_size(): void
    {
        $product = Product::factory()->create();
        $product->variants()->create(['color' => 'Black', 'size' => '40', 'available_quantity' => 5]);

        $this->assertTrue(ProductVariant::existsFor($product, ' black ', '40'));
        $this->assertFalse(ProductVariant::existsFor($product, 'Black', '41'));
    }
}
