<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VariantErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create(['size_enabled' => true]);
    }

    private function lookups(): void
    {
        foreach (['Brown', 'Black'] as $index => $color) {
            VariantColor::create(['name' => $color, 'sort_order' => $index, 'active' => true]);
        }

        foreach (['40', '41'] as $index => $size) {
            VariantSize::create(['name' => $size, 'sort_order' => $index, 'active' => true]);
        }
    }

    private function variant(Product $product, string $color, string $size, int $quantity = 5): ProductVariant
    {
        return $product->variants()->create([
            'color' => $color,
            'size' => $size,
            'available_quantity' => $quantity,
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

    private function orderWithVariant(ProductVariant $variant): Order
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        $order->items()->create(OrderItem::snapshotFromVariant($variant) + ['quantity' => 2]);
        $order->recalculateTotalQuantity();

        return $order->refresh();
    }

    public function test_unique_variant_creation_succeeds_from_the_page(): void
    {
        $product = $this->product();
        $this->lookups();

        $this->relationManager($product)
            ->callAction(TestAction::make('create')->table(), data: [
                'color' => 'Brown',
                'size' => '40',
                'available_quantity' => 5,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'color' => 'Brown',
            'size' => '40',
            'available_quantity' => 5,
        ]);
    }

    public function test_duplicate_variant_creation_is_rejected_with_an_arabic_message(): void
    {
        $product = $this->product();
        $this->lookups();
        $this->variant($product, 'Brown', '40');

        $component = $this->relationManager($product)
            ->callAction(TestAction::make('create')->table(), data: [
                'color' => 'Brown',
                'size' => '40',
                'available_quantity' => 9,
            ])
            ->assertHasActionErrors(['color']);

        $this->assertStringContainsString(
            'هذا اللون والمقاس مضافان بالفعل لهذا المنتج.',
            implode(' | ', $component->errors()->all()),
        );

        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(5, $product->variants()->where('color', 'Brown')->value('available_quantity'));
    }

    public function test_duplicate_variant_edit_is_rejected(): void
    {
        $product = $this->product();
        $this->lookups();
        $this->variant($product, 'Brown', '40', 5);
        $second = $this->variant($product, 'Brown', '41', 7);

        $this->relationManager($product)
            ->callTableAction('edit', $second, data: [
                'color' => 'Brown',
                'size' => '40',
                'available_quantity' => 7,
            ])
            ->assertHasActionErrors(['color']);

        $this->assertSame('41', $second->refresh()->size);
        $this->assertSame(2, $product->variants()->count());
    }

    public function test_editing_a_variant_without_changing_its_combination_is_allowed(): void
    {
        $product = $this->product();
        $this->lookups();
        $variant = $this->variant($product, 'Brown', '40', 5);

        $this->relationManager($product)
            ->callTableAction('edit', $variant, data: [
                'color' => 'Brown',
                'size' => '40',
                'available_quantity' => 11,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(11, $variant->refresh()->available_quantity);
    }

    public function test_database_unique_constraint_remains_the_final_protection(): void
    {
        $product = $this->product();
        $this->variant($product, 'Brown', '40');

        $this->expectException(QueryException::class);

        $this->variant($product, 'Brown', '40');
    }

    public function test_duplicate_unsized_variant_is_rejected_for_size_disabled_products(): void
    {
        $product = Product::factory()->create(['size_enabled' => false]);
        VariantColor::create(['name' => 'Brown', 'sort_order' => 0, 'active' => true]);
        $product->variants()->create(['color' => 'Brown', 'size' => null, 'available_quantity' => 5]);

        $this->relationManager($product)
            ->callAction(TestAction::make('create')->table(), data: [
                'color' => 'Brown',
                'available_quantity' => 9,
            ])
            ->assertHasActionErrors(['color']);

        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(5, $product->variants()->first()->available_quantity);
    }

    public function test_protected_variant_deletion_is_cancelled_with_a_notification(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'Brown', '40');
        $this->orderWithVariant($variant);

        $this->relationManager($product)
            ->callTableAction('delete', $variant)
            ->assertNotified('تعذّر حذف المقاس');

        $this->assertDatabaseHas('product_variants', ['id' => $variant->getKey()]);
    }

    public function test_unprotected_variant_deletion_succeeds(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'Brown', '40');

        $this->relationManager($product)
            ->callTableAction('delete', $variant);

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->getKey()]);
    }

    public function test_bulk_variant_deletion_with_a_protected_variant_deletes_nothing(): void
    {
        $product = $this->product();
        $protected = $this->variant($product, 'Brown', '40');
        $free = $this->variant($product, 'Black', '41');
        $this->orderWithVariant($protected);

        $this->relationManager($product)
            ->callTableBulkAction('delete', [$protected, $free])
            ->assertNotified('تعذّر حذف الأصناف المحددة');

        $this->assertDatabaseHas('product_variants', ['id' => $protected->getKey()]);
        $this->assertDatabaseHas('product_variants', ['id' => $free->getKey()]);
    }

    public function test_bulk_variant_deletion_without_protection_succeeds(): void
    {
        $product = $this->product();
        $first = $this->variant($product, 'Brown', '40');
        $second = $this->variant($product, 'Black', '41');

        $this->relationManager($product)
            ->callTableBulkAction('delete', [$first, $second]);

        $this->assertDatabaseMissing('product_variants', ['id' => $first->getKey()]);
        $this->assertDatabaseMissing('product_variants', ['id' => $second->getKey()]);
    }
}
