<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProtectedDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function productWithVariant(): array
    {
        $product = Product::factory()->create(['size_enabled' => true]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '40',
            'available_quantity' => 5,
        ]);

        return [$product, $variant];
    }

    private function freeProduct(): Product
    {
        return Product::factory()->create(['size_enabled' => true]);
    }

    private function orderFor(ProductVariant $variant, ?Customer $customer = null): Order
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => ($customer ?? Customer::factory()->create())->id,
            'total_quantity' => 0,
        ]);

        $order->items()->create(OrderItem::snapshotFromVariant($variant) + ['quantity' => 1]);
        $order->recalculateTotalQuantity();

        return $order->refresh();
    }

    public function test_protected_product_deletion_is_cancelled_with_a_notification(): void
    {
        [$product, $variant] = $this->productWithVariant();
        $this->orderFor($variant);

        Livewire::actingAs(User::factory()->create())
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction('delete')
            ->assertNotified('تعذّر حذف المنتج');

        $this->assertDatabaseHas('products', ['id' => $product->getKey()]);
    }

    public function test_bulk_product_deletion_with_a_protected_product_deletes_nothing(): void
    {
        [$protected, $variant] = $this->productWithVariant();
        $free = $this->freeProduct();
        $this->orderFor($variant);

        Livewire::actingAs(User::factory()->create())
            ->test(ListProducts::class)
            ->callTableBulkAction('delete', [$protected, $free])
            ->assertNotified('تعذّر حذف المنتجات المحددة');

        $this->assertDatabaseHas('products', ['id' => $protected->getKey()]);
        $this->assertDatabaseHas('products', ['id' => $free->getKey()]);
    }

    public function test_bulk_product_deletion_without_protection_succeeds(): void
    {
        $first = $this->freeProduct();
        $second = $this->freeProduct();

        Livewire::actingAs(User::factory()->create())
            ->test(ListProducts::class)
            ->callTableBulkAction('delete', [$first, $second]);

        $this->assertDatabaseMissing('products', ['id' => $first->getKey()]);
        $this->assertDatabaseMissing('products', ['id' => $second->getKey()]);
    }

    public function test_protected_customer_deletion_is_cancelled_with_a_notification(): void
    {
        $customer = Customer::factory()->create();
        [, $variant] = $this->productWithVariant();
        $this->orderFor($variant, $customer);

        Livewire::actingAs(User::factory()->create())
            ->test(EditCustomer::class, ['record' => $customer->getKey()])
            ->callAction('delete')
            ->assertNotified('تعذّر حذف العميل');

        $this->assertDatabaseHas('customers', ['id' => $customer->getKey()]);
    }

    public function test_bulk_customer_deletion_with_a_protected_customer_deletes_nothing(): void
    {
        $protected = Customer::factory()->create();
        $free = Customer::factory()->create();
        [, $variant] = $this->productWithVariant();
        $this->orderFor($variant, $protected);

        Livewire::actingAs(User::factory()->create())
            ->test(ListCustomers::class)
            ->callTableBulkAction('delete', [$protected, $free])
            ->assertNotified('تعذّر حذف العملاء المحددين');

        $this->assertDatabaseHas('customers', ['id' => $protected->getKey()]);
        $this->assertDatabaseHas('customers', ['id' => $free->getKey()]);
    }

    public function test_unprotected_customer_deletion_succeeds(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(EditCustomer::class, ['record' => $customer->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('customers', ['id' => $customer->getKey()]);
    }
}
