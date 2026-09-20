<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_persists(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

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
        $product = Product::factory()->create(['size_enabled' => true]);

        $product->variants()->createMany([
            ['color' => 'Black', 'size' => '40', 'available_quantity' => 15],
            ['color' => 'Black', 'size' => '41', 'available_quantity' => 10],
            ['color' => 'White', 'size' => '40', 'available_quantity' => 8],
        ]);

        $this->assertSame(3, $product->variants()->count());
    }

    public function test_variant_combination_is_unique_per_product(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

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
        $productA = Product::factory()->create(['size_enabled' => true]);
        $productB = Product::factory()->create(['size_enabled' => true]);

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
        $product = Product::factory()->create(['size_enabled' => true]);

        $this->expectException(ValidationException::class);

        ProductVariant::validate([
            'color' => '   ',
            'size' => "\t",
            'available_quantity' => 5,
        ], $product);
    }

    public function test_size_is_required_when_product_size_is_enabled(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $this->expectException(ValidationException::class);

        ProductVariant::validate([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ], $product);
    }

    public function test_size_is_optional_when_product_size_is_disabled(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $data = ProductVariant::validate([
            'color' => 'Black',
            'size' => '   ',
            'available_quantity' => 5,
        ], $product);

        $this->assertNull($data['size']);
    }

    public function test_unsized_variant_persists_null_size_without_a_fake_value(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '',
            'available_quantity' => 5,
        ]);

        $this->assertNull($variant->refresh()->size);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
            'size' => null,
        ]);
        $this->assertArrayNotHasKey('size_key', $variant->toArray());
    }

    public function test_duplicate_unsized_variants_are_rejected_by_the_database(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ]);

        $this->expectException(QueryException::class);

        $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 7,
        ]);
    }

    public function test_unsized_variant_is_rejected_when_product_size_is_enabled(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $this->expectException(ValidationException::class);

        $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ]);
    }

    public function test_non_null_size_is_rejected_when_product_size_is_disabled(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $this->expectException(ValidationException::class);

        $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 5,
        ]);
    }

    public function test_whitespace_only_size_is_rejected_for_size_enabled_products(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $this->expectException(ValidationException::class);

        $product->variants()->create([
            'color' => 'Black',
            'size' => "  \t ",
            'available_quantity' => 5,
        ]);
    }

    public function test_generated_size_key_distinguishes_null_and_sized_keys_at_database_level(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        // Raw inserts bypass the application guard on purpose: this proves the
        // generated size_key uniqueness behavior only, not an allowed domain state.
        DB::table('product_variants')->insert([
            [
                'product_id' => $product->getKey(),
                'color' => 'Black',
                'size' => null,
                'available_quantity' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $product->getKey(),
                'color' => 'Black',
                'size' => '41',
                'available_quantity' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(1, $product->variants()->whereNull('size')->count());
    }

    public function test_mixed_variant_state_is_unreachable_through_application_code(): void
    {
        $sized = Product::factory()->create(['size_enabled' => true]);
        $sized->variants()->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 1]);

        try {
            $sized->variants()->create(['color' => 'White', 'size' => null, 'available_quantity' => 1]);
            $this->fail('Expected ValidationException for an unsized variant on a size-enabled product');
        } catch (ValidationException) {
        }

        $unsized = Product::factory()->create(['size_enabled' => false]);
        $unsized->variants()->create(['color' => 'Black', 'size' => null, 'available_quantity' => 1]);

        try {
            $unsized->variants()->create(['color' => 'White', 'size' => '41', 'available_quantity' => 1]);
            $this->fail('Expected ValidationException for a sized variant on a size-disabled product');
        } catch (ValidationException) {
        }

        $this->assertSame(1, $sized->variants()->count());
        $this->assertSame(1, $unsized->variants()->count());
        $this->assertSame(0, $sized->variants()->whereNull('size')->count());
        $this->assertSame(0, $unsized->variants()->whereNotNull('size')->count());
    }

    public function test_disabling_size_is_blocked_while_sized_variants_exist(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 5,
        ]);

        try {
            $product->update(['size_enabled' => false]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('size_enabled', $exception->errors());
        }

        $this->assertTrue($product->refresh()->size_enabled);
    }

    public function test_direct_model_assignment_cannot_disable_size_while_sized_variants_exist(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 5,
        ]);

        $this->expectException(ValidationException::class);

        $product->forceFill(['size_enabled' => false])->save();
    }

    public function test_disabling_size_is_allowed_without_sized_variants(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $product->update(['size_enabled' => false]);

        $this->assertFalse($product->refresh()->size_enabled);
    }

    public function test_enabling_size_is_allowed_without_variants(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $product->update(['size_enabled' => true]);

        $this->assertTrue($product->refresh()->size_enabled);
    }

    public function test_enabling_size_is_blocked_while_unsized_variants_exist(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ]);

        try {
            $product->update(['size_enabled' => true]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('size_enabled', $exception->errors());
        }

        $this->assertFalse($product->refresh()->size_enabled);
    }

    public function test_direct_model_assignment_cannot_enable_size_while_unsized_variants_exist(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);

        $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ]);

        $this->expectException(ValidationException::class);

        $product->forceFill(['size_enabled' => true])->save();
    }

    public function test_color_and_size_are_trimmed_on_save(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $variant = $product->variants()->create([
            'color' => '  Black  ',
            'size' => ' 41 ',
            'available_quantity' => 5,
        ]);

        $variant->refresh();

        $this->assertSame('Black', $variant->color);
        $this->assertSame('41', $variant->size);
    }

    public function test_product_variants_relationship_works_both_directions(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $variant = ProductVariant::factory()->for($product)->create();

        $this->assertTrue($variant->product->is($product));
        $this->assertTrue($product->variants->first()->is($variant));
    }

    public function test_deleting_product_cascades_variants(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
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
        $product = Product::factory()->create(['size_enabled' => true]);
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

        $product = Product::factory()->create(['size_enabled' => true]);
        $variant = $product->variants()->create([
            'color' => 'Red',
            'size' => '40',
            'available_quantity' => 4,
        ]);

        $options = VariantsRelationManager::colorOptions($variant);

        $this->assertArrayHasKey('Red', $options);
        $this->assertStringContainsString('Red', (string) $variant->color);
        $this->assertFalse($color->active);
    }

    public function test_color_is_required_when_product_color_is_enabled(): void
    {
        $product = Product::factory()->create(['color_enabled' => true]);

        $this->expectException(ValidationException::class);

        ProductVariant::validate([
            'color' => null,
            'size' => null,
            'available_quantity' => 5,
        ], $product);
    }

    public function test_color_is_optional_when_product_color_is_disabled(): void
    {
        $product = Product::factory()->create(['color_enabled' => false]);

        $data = ProductVariant::validate([
            'color' => '   ',
            'size' => null,
            'available_quantity' => 5,
        ], $product);

        $this->assertNull($data['color']);
    }

    public function test_uncolored_variant_persists_null_color_without_a_fake_value(): void
    {
        $product = Product::factory()->create(['color_enabled' => false]);

        $variant = $product->variants()->create([
            'color' => '',
            'size' => null,
            'available_quantity' => 5,
        ]);

        $this->assertNull($variant->refresh()->color);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
            'color' => null,
        ]);
        $this->assertArrayNotHasKey('color_key', $variant->toArray());
    }

    public function test_uncolored_variant_is_rejected_when_product_color_is_enabled(): void
    {
        $product = Product::factory()->create(['color_enabled' => true]);

        $this->expectException(ValidationException::class);

        $product->variants()->create([
            'color' => null,
            'size' => null,
            'available_quantity' => 5,
        ]);
    }

    public function test_non_null_color_is_rejected_when_product_color_is_disabled(): void
    {
        $product = Product::factory()->create(['color_enabled' => false]);

        $this->expectException(ValidationException::class);

        $product->variants()->create([
            'color' => 'Black',
            'size' => null,
            'available_quantity' => 5,
        ]);
    }

    public function test_duplicate_uncolored_variants_are_rejected_by_the_database(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);

        $product->variants()->create([
            'color' => null,
            'size' => null,
            'available_quantity' => 5,
        ]);

        $this->expectException(QueryException::class);

        $product->variants()->create([
            'color' => null,
            'size' => null,
            'available_quantity' => 7,
        ]);
    }

    public function test_unique_index_covers_all_four_color_size_combinations(): void
    {
        $combinations = [
            [
                'color_enabled' => true,
                'size_enabled' => true,
                'first' => ['color' => 'Black', 'size' => '40'],
                'second' => ['color' => 'Black', 'size' => '41'],
            ],
            [
                'color_enabled' => true,
                'size_enabled' => false,
                'first' => ['color' => 'Black', 'size' => null],
                'second' => ['color' => 'White', 'size' => null],
            ],
            [
                'color_enabled' => false,
                'size_enabled' => true,
                'first' => ['color' => null, 'size' => '40'],
                'second' => ['color' => null, 'size' => '41'],
            ],
            [
                'color_enabled' => false,
                'size_enabled' => false,
                'first' => ['color' => null, 'size' => null],
                'second' => null,
            ],
        ];

        foreach ($combinations as $index => $combination) {
            $product = Product::factory()->create([
                'color_enabled' => $combination['color_enabled'],
                'size_enabled' => $combination['size_enabled'],
            ]);

            $product->variants()->create($combination['first'] + ['available_quantity' => 1]);

            if ($combination['second'] !== null) {
                $product->variants()->create($combination['second'] + ['available_quantity' => 2]);
            }

            try {
                $product->variants()->create($combination['first'] + ['available_quantity' => 3]);
                $this->fail("Expected QueryException for duplicate combination #{$index}");
            } catch (QueryException) {
            }

            $this->assertSame(
                $combination['second'] === null ? 1 : 2,
                $product->variants()->count(),
                "Unexpected variant count for combination #{$index}",
            );
        }
    }

    public function test_generated_color_key_distinguishes_null_and_colored_keys_at_database_level(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);

        // Raw inserts bypass the application guard on purpose: this proves the
        // generated color_key uniqueness behavior only, not an allowed domain state.
        DB::table('product_variants')->insert([
            [
                'product_id' => $product->getKey(),
                'color' => null,
                'size' => null,
                'available_quantity' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $product->getKey(),
                'color' => 'Black',
                'size' => null,
                'available_quantity' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(1, $product->variants()->whereNull('color')->count());
    }
}
