<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariantGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_uses_availability_dimensions_not_choice_toggles(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);

        foreach (['37', '38', '39', '40', '41'] as $index => $size) {
            VariantSize::create(['name' => $size, 'sort_order' => $index + 1, 'active' => true]);
        }

        $result = ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 10],
            ['color' => 'White', 'quantity' => 10],
        ]);

        $this->assertSame(['created' => 10, 'skipped' => 0], $result);
        $this->assertSame(10, $product->variants()->count());
    }

    public function test_generation_uses_all_five_active_default_sizes(): void
    {
        $product = Product::factory()->create();

        foreach (['37', '38', '39', '40', '41'] as $index => $size) {
            VariantSize::create(['name' => $size, 'sort_order' => $index + 1, 'active' => true]);
        }

        $result = ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 5],
        ]);

        $this->assertSame(['created' => 5, 'skipped' => 0], $result);
        $this->assertSame(['37', '38', '39', '40', '41'], $product->variants()->orderBy('size')->pluck('size')->all());
    }

    public function test_generation_requires_exactly_five_active_default_sizes(): void
    {
        $product = Product::factory()->create();
        VariantSize::create(['name' => '40', 'sort_order' => 1, 'active' => true]);

        $this->expectException(ValidationException::class);

        ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 5],
        ]);
    }
}
