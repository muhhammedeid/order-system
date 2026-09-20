<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_uses_availability_dimensions_not_choice_toggles(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);
        VariantSize::create(['name' => '40', 'sort_order' => 1, 'active' => true]);
        VariantSize::create(['name' => '41', 'sort_order' => 2, 'active' => true]);

        $result = ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 10],
            ['color' => 'White', 'quantity' => 10],
        ], ['40', '41']);

        $this->assertSame(['created' => 4, 'skipped' => 0], $result);
        $this->assertSame(4, $product->variants()->count());
    }

    public function test_empty_size_selection_creates_color_only_variant(): void
    {
        $product = Product::factory()->create();

        $result = ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 5],
        ], []);

        $this->assertSame(['created' => 1, 'skipped' => 0], $result);
        $this->assertNull($product->variants()->firstOrFail()->size);
    }
}

