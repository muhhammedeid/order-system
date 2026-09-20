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

    private function sizes(array $names, bool $active = true): void
    {
        foreach ($names as $index => $name) {
            VariantSize::create([
                'name' => $name,
                'sort_order' => $index,
                'active' => $active,
            ]);
        }
    }

    public function test_size_enabled_product_generates_one_variant_per_color_and_size(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['37', '38', '39', '40', '41']);

        $result = ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 10],
            ['color' => 'White', 'quantity' => 10],
            ['color' => 'Brown', 'quantity' => 10],
        ]);

        $this->assertSame(['created' => 15, 'skipped' => 0], $result);
        $this->assertSame(15, $product->variants()->count());
        $this->assertSame(3, $product->variants()->distinct()->count('color'));

        foreach (['Black', 'White', 'Brown'] as $color) {
            $this->assertSame(5, $product->variants()->where('color', $color)->count());
        }
    }

    public function test_each_color_uses_its_own_default_quantity(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['37', '38', '39', '40', '41']);

        ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 200],
            ['color' => 'White', 'quantity' => 120],
        ]);

        $this->assertSame(5, $product->variants()->where('color', 'Black')->where('available_quantity', 200)->count());
        $this->assertSame(5, $product->variants()->where('color', 'White')->where('available_quantity', 120)->count());
    }

    public function test_overriding_one_variant_does_not_change_siblings(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['37', '38', '39', '40', '41']);
        ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 200]]);

        $variant = $product->variants()->where('size', '40')->firstOrFail();
        $variant->update(['available_quantity' => 50]);

        $this->assertSame(50, $variant->refresh()->available_quantity);
        $this->assertSame(1, $product->variants()->where('size', '40')->where('available_quantity', 50)->count());

        foreach (['37', '38', '39', '41'] as $size) {
            $this->assertSame(200, $product->variants()->where('size', $size)->value('available_quantity'));
        }
    }

    public function test_adding_a_new_color_does_not_modify_existing_variants(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['37', '38', '39', '40', '41']);
        ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 200],
            ['color' => 'White', 'quantity' => 120],
        ]);

        $existing = $product->variants()->orderBy('id')->get(['id', 'color', 'size', 'available_quantity'])->toArray();

        $result = ProductVariant::generateMissing($product, [['color' => 'Beige', 'quantity' => 100]]);

        $this->assertSame(['created' => 5, 'skipped' => 0], $result);
        $this->assertSame(5, $product->variants()->where('color', 'Beige')->count());
        $this->assertSame(5, $product->variants()->where('color', 'Beige')->where('available_quantity', 100)->count());
        $this->assertSame(15, $product->variants()->count());

        $this->assertSame(
            $existing,
            $product->variants()->whereIn('color', ['Black', 'White'])->orderBy('id')->get(['id', 'color', 'size', 'available_quantity'])->toArray(),
        );
    }

    public function test_repeated_generation_creates_no_duplicates_and_keeps_quantities(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['40', '41']);

        ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 200]]);
        $this->assertSame(200, $product->variants()->where('size', '40')->value('available_quantity'));

        $result = ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 999]]);

        $this->assertSame(['created' => 0, 'skipped' => 2], $result);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(200, $product->variants()->where('size', '40')->value('available_quantity'));
        $this->assertSame(200, $product->variants()->where('size', '41')->value('available_quantity'));
    }

    public function test_size_disabled_products_generate_one_unsized_variant_per_color(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);
        $this->sizes(['40', '41']);

        $result = ProductVariant::generateMissing($product, [
            ['color' => 'Black', 'quantity' => 200],
            ['color' => 'White', 'quantity' => 100],
        ], ['40']);

        $this->assertSame(['created' => 2, 'skipped' => 0], $result);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(2, $product->variants()->whereNull('size')->count());
        $this->assertSame(200, $product->variants()->where('color', 'Black')->value('available_quantity'));
        $this->assertSame(100, $product->variants()->where('color', 'White')->value('available_quantity'));
    }

    public function test_generation_fails_when_size_enabled_product_has_no_active_sizes(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        VariantSize::create(['name' => '40', 'sort_order' => 0, 'active' => false]);

        try {
            ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 10]]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sizes', $exception->errors());
        }

        $this->assertSame(0, $product->variants()->count());
    }

    public function test_selected_sizes_are_limited_to_active_sizes_server_side(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['40', '41']);

        $result = ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 5]], ['41', '99']);

        $this->assertSame(['created' => 1, 'skipped' => 0], $result);
        $this->assertSame(1, $product->variants()->where('size', '41')->count());
        $this->assertSame(0, $product->variants()->where('size', '40')->count());

        try {
            ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 5]], ['99']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        $this->assertSame(1, $product->variants()->count());
    }

    public function test_changing_active_sizes_does_not_mutate_existing_variants(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['37', '38', '39', '40', '41']);
        ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 200]]);

        VariantSize::where('name', '41')->update(['active' => false]);
        VariantSize::create(['name' => '42', 'sort_order' => 6, 'active' => true]);

        $this->assertSame(5, $product->variants()->count());
        $this->assertSame(200, $product->variants()->where('size', '41')->value('available_quantity'));

        $result = ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 50]]);

        $this->assertSame(['created' => 1, 'skipped' => 4], $result);
        $this->assertSame(6, $product->variants()->count());
        $this->assertSame(50, $product->variants()->where('size', '42')->value('available_quantity'));
        $this->assertSame(200, $product->variants()->where('size', '41')->value('available_quantity'));
    }

    public function test_generation_treats_existing_colors_case_insensitively(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['40']);

        ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 10]]);

        $result = ProductVariant::generateMissing($product, [['color' => '  black  ', 'quantity' => 20]]);

        $this->assertSame(['created' => 0, 'skipped' => 1], $result);
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(10, $product->variants()->first()->available_quantity);
        $this->assertSame('Black', $product->variants()->first()->color);
    }

    public function test_mixed_valid_and_invalid_batch_creates_zero_variants(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['40', '41']);

        try {
            ProductVariant::generateMissing($product, [
                ['color' => 'Black', 'quantity' => 200],
                ['color' => 'White', 'quantity' => -1],
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        $this->assertSame(0, $product->variants()->count());
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_mixed_valid_and_invalid_batch_leaves_preexisting_variants_untouched(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->sizes(['40', '41']);
        ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 200]]);

        try {
            ProductVariant::generateMissing($product, [
                ['color' => 'White', 'quantity' => 50],
                ['color' => 'Brown', 'quantity' => 'abc'],
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(200, $product->variants()->where('color', 'Black')->value('available_quantity'));
        $this->assertSame(0, $product->variants()->where('color', 'White')->count());
        $this->assertSame(0, $product->variants()->where('color', 'Brown')->count());
    }

    public function test_negative_default_quantity_is_rejected_server_side(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $this->expectException(ValidationException::class);

        ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => -5]]);
    }

    public function test_quantity_above_the_unsigned_range_is_rejected(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $this->expectException(ValidationException::class);

        ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 4294967296]]);
    }

    public function test_empty_color_rows_are_rejected(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $this->expectException(ValidationException::class);

        ProductVariant::generateMissing($product, []);
    }

    public function test_color_disabled_size_enabled_products_generate_one_uncolored_variant_per_size(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => true]);
        $this->sizes(['40', '41']);

        $result = ProductVariant::generateMissing($product, [
            ['quantity' => 50],
        ]);

        $this->assertSame(['created' => 2, 'skipped' => 0], $result);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(2, $product->variants()->whereNull('color')->count());
        $this->assertSame(50, $product->variants()->where('size', '40')->value('available_quantity'));
        $this->assertSame(50, $product->variants()->where('size', '41')->value('available_quantity'));
    }

    public function test_both_dimensions_disabled_products_generate_exactly_one_variant(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);

        $result = ProductVariant::generateMissing($product, [
            ['quantity' => 25],
        ]);

        $this->assertSame(['created' => 1, 'skipped' => 0], $result);
        $this->assertSame(1, $product->variants()->count());
        $this->assertNull($product->variants()->first()->color);
        $this->assertNull($product->variants()->first()->size);

        $repeat = ProductVariant::generateMissing($product, [['quantity' => 99]]);

        $this->assertSame(['created' => 0, 'skipped' => 1], $repeat);
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(25, $product->variants()->value('available_quantity'));
    }

    public function test_generation_rejects_a_color_for_color_disabled_products(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);

        try {
            ProductVariant::generateMissing($product, [['color' => 'Black', 'quantity' => 10]]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('color', $exception->errors());
        }

        $this->assertSame(0, $product->variants()->count());
    }
}
