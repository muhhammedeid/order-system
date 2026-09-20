<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VariantManagementPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeSize(string $name, int $sortOrder = 0, bool $active = true): VariantSize
    {
        return VariantSize::create([
            'name' => $name,
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);
    }

    private function relationManager(Product $product)
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(VariantsRelationManager::class, [
                'ownerRecord' => $product,
                'pageClass' => EditProduct::class,
            ]);
    }

    public function test_generation_action_creates_missing_variants_from_the_page(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->makeSize('40', 0);
        $this->makeSize('41', 1);
        VariantColor::create(['name' => 'Black', 'sort_order' => 0, 'active' => true]);

        $this->relationManager($product)
            ->callAction('generateVariants', data: [
                'colors' => [
                    ['color' => 'Black', 'quantity' => 200],
                ],
                'sizes' => ['40', '41'],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(2, $product->variants()->where('color', 'Black')->where('available_quantity', 200)->count());
    }

    public function test_generation_action_is_idempotent_from_the_page(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->makeSize('40', 0);
        VariantColor::create(['name' => 'Black', 'sort_order' => 0, 'active' => true]);

        foreach ([0, 1] as $run) {
            $this->relationManager($product)
                ->callAction('generateVariants', data: [
                    'colors' => [
                        ['color' => 'Black', 'quantity' => 100],
                    ],
                    'sizes' => ['40'],
                ])
                ->assertHasNoActionErrors()
                ->assertNotified();
        }

        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(100, $product->variants()->value('available_quantity'));
    }

    public function test_generation_action_validates_the_submitted_rows_before_creating_anything(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $this->makeSize('40', 0);
        VariantColor::create(['name' => 'Black', 'sort_order' => 0, 'active' => true]);

        $this->relationManager($product)
            ->callAction('generateVariants', data: [
                'colors' => [
                    ['color' => 'Black', 'quantity' => -1],
                ],
                'sizes' => ['40'],
            ])
            ->assertHasActionErrors(['colors.0.quantity']);

        $this->assertSame(0, $product->variants()->count());
    }

    public function test_generation_action_creates_unsized_variants_for_size_disabled_products(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);
        VariantColor::create(['name' => 'Black', 'sort_order' => 0, 'active' => true]);

        $this->relationManager($product)
            ->callAction('generateVariants', data: [
                'colors' => [
                    ['color' => 'Black', 'quantity' => 0],
                ],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(1, $product->variants()->count());
        $this->assertNull($product->variants()->first()->size);
        $this->assertSame(0, $product->variants()->first()->available_quantity);
    }

    public function test_inline_quantity_update_persists_server_side(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'color' => 'Black',
            'size' => '40',
            'available_quantity' => 10,
        ]);

        $this->relationManager($product)
            ->call('updateTableColumnState', 'available_quantity', (string) $variant->getKey(), '30');

        $this->assertSame(30, $variant->refresh()->available_quantity);
    }

    public function test_inline_quantity_update_rejects_negative_values(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'color' => 'Black',
            'size' => '40',
            'available_quantity' => 10,
        ]);

        $this->relationManager($product)
            ->call('updateTableColumnState', 'available_quantity', (string) $variant->getKey(), '-5');

        $this->assertSame(10, $variant->refresh()->available_quantity);
    }

    public function test_inline_quantity_update_rejects_values_above_the_unsigned_range(): void
    {
        $product = Product::factory()->create(['size_enabled' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'color' => 'Black',
            'size' => '40',
            'available_quantity' => 10,
        ]);

        $this->relationManager($product)
            ->call('updateTableColumnState', 'available_quantity', (string) $variant->getKey(), '4294967296');

        $this->assertSame(10, $variant->refresh()->available_quantity);
    }
}
