<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_persists(): void
    {
        $product = Product::factory()->create();

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 15,
        ]);

        $variant->refresh();

        $this->assertSame('Black', $variant->color);
        $this->assertSame('41', $variant->size);
        $this->assertSame(15, $variant->available_quantity);
        $this->assertTrue($variant->product->is($product));
    }

    public function test_multiple_variants_per_product_persist(): void
    {
        $product = Product::factory()->create();

        $product->variants()->createMany([
            ['color' => 'Black', 'size' => '40', 'available_quantity' => 15],
            ['color' => 'Black', 'size' => '41', 'available_quantity' => 10],
            ['color' => 'White', 'size' => '40', 'available_quantity' => 8],
        ]);

        $this->assertSame(3, $product->variants()->count());
    }

    public function test_variant_combination_is_unique_per_product(): void
    {
        $product = Product::factory()->create();

        $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 5,
        ]);

        $this->expectException(QueryException::class);

        $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 9,
        ]);
    }

    public function test_same_combination_allowed_for_different_products(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        foreach ([$productA, $productB] as $product) {
            $product->variants()->create([
                'color' => 'Black',
                'size' => '40',
                'available_quantity' => 3,
            ]);
        }

        $this->assertSame(1, $productA->variants()->count());
        $this->assertSame(1, $productB->variants()->count());
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        ProductVariant::validate([
            'color' => 'Black',
            'size' => '40',
            'available_quantity' => -1,
        ]);
    }

    public function test_whitespace_only_color_and_size_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        ProductVariant::validate([
            'color' => '   ',
            'size' => "\t",
            'available_quantity' => 5,
        ]);
    }

    public function test_product_variants_relationship_works_both_directions(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->assertTrue($variant->product->is($product));
        $this->assertTrue($product->variants->first()->is($variant));
    }

    public function test_deleting_product_cascades_variants(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $product->delete();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->getKey()]);
    }

    public function test_variant_color_name_must_be_unique(): void
    {
        VariantColor::create(['name' => 'Black', 'sort_order' => 0, 'active' => true]);

        $this->expectException(QueryException::class);

        VariantColor::create(['name' => 'Black', 'sort_order' => 1, 'active' => true]);
    }

    public function test_variant_size_name_must_be_unique(): void
    {
        VariantSize::create(['name' => '41', 'sort_order' => 0, 'active' => true]);

        $this->expectException(QueryException::class);

        VariantSize::create(['name' => '41', 'sort_order' => 1, 'active' => true]);
    }

    public function test_deactivating_lookups_does_not_alter_existing_variants(): void
    {
        $product = Product::factory()->create();
        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 12,
        ]);

        VariantColor::create(['name' => 'Black', 'sort_order' => 0, 'active' => true]);
        VariantSize::create(['name' => '41', 'sort_order' => 0, 'active' => true]);

        VariantColor::where('name', 'Black')->update(['active' => false]);
        VariantSize::where('name', '41')->update(['active' => false]);

        $variant->refresh();

        $this->assertSame('Black', $variant->color);
        $this->assertSame('41', $variant->size);
        $this->assertSame(12, $variant->available_quantity);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_inactive_lookup_value_remains_selectable_for_existing_variant(): void
    {
        $color = VariantColor::create(['name' => 'Red', 'sort_order' => 0, 'active' => false]);

        $product = Product::factory()->create();
        $variant = $product->variants()->create([
            'color' => 'Red',
            'size' => '40',
            'available_quantity' => 4,
        ]);

        $options = \App\Filament\Resources\Products\RelationManagers\VariantsRelationManager::colorOptions($variant);

        $this->assertArrayHasKey('Red', $options);
        $this->assertStringContainsString('Red', (string) $variant->color);
        $this->assertFalse($color->active);
    }
}
