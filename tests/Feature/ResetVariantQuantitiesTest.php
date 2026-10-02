<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetVariantQuantitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_sets_every_variant_to_fifty_without_changing_product_or_variant_identity(): void
    {
        $active = Product::factory()->create(['active' => true]);
        $inactive = Product::factory()->create(['active' => false]);
        $variants = collect([
            ProductVariant::factory()->create(['product_id' => $active->id, 'available_quantity' => 0]),
            ProductVariant::factory()->create(['product_id' => $inactive->id, 'available_quantity' => 150]),
            ProductVariant::factory()->create(['available_quantity' => 50]),
        ]);

        $this->artisan('products:reset-variant-quantities')->assertSuccessful();

        foreach ($variants as $variant) {
            $this->assertDatabaseHas('product_variants', [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'color' => $variant->color,
                'size' => $variant->size,
                'available_quantity' => 50,
            ]);
        }

        $this->assertDatabaseCount('product_variants', 3);
        $this->assertDatabaseHas('products', ['id' => $active->id, 'product_code' => $active->product_code, 'active' => true]);
        $this->assertDatabaseHas('products', ['id' => $inactive->id, 'product_code' => $inactive->product_code, 'active' => false]);
    }
}
