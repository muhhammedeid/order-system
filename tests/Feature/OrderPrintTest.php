<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderManagement\Pages\ConfirmOrder;
use App\Filament\Resources\OrderManagement\Pages\ViewOrder as ManagementViewOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder as DeliveredViewOrder;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class OrderPrintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Order, 1: ProductVariant, 2: Product}
     */
    private function publicOrder(int $quantity = 3): array
    {
        $product = Product::factory()->create([
            'name' => 'Snapshot Shoe',
            'product_code' => 'SNP-1',
            'price_visibility' => 'public',
            'price' => 450,
            'size_enabled' => true,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 10,
        ]);

        $customer = Customer::factory()->create([
            'name' => 'متجر الاختبار',
            'company_name' => 'شركة الاختبار',
            'phone' => '01001234567',
            'whatsapp' => '01001234567',
            'governorate' => 'القاهرة',
            'city' => 'مدينة نصر',
            'address' => 'شارع الاختبار',
        ]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => $customer->id,
            'total_quantity' => 0,
        ]);

        $order->items()->create(OrderItem::snapshotFromVariant($variant) + ['quantity' => $quantity]);
        $order->recalculateTotalQuantity();

        return [$order->refresh(), $variant, $product];
    }

    private function requestPriceItem(Order $order, int $quantity = 2): void
    {
        $product = Product::factory()->requestPrice()->create([
            'name' => 'Hidden Price Shoe',
            'product_code' => 'HID-1',
            'price' => 9876.54,
            'size_enabled' => true,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Navy',
            'size' => '42',
            'available_quantity' => 1,
        ]);

        $order->items()->create(OrderItem::snapshotFromVariant($variant) + ['quantity' => $quantity]);
        $order->recalculateTotalQuantity();
    }

    private function printUrl(Order $order): string
    {
        return route('filament.admin.orders.print', ['order' => $order]);
    }

    public function test_print_action_is_exposed_on_all_order_views(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->publicOrder();

        Livewire::actingAs($admin)
            ->test(ManagementViewOrder::class, ['record' => $order->getKey()])
            ->assertActionExists('printInvoice');

        Livewire::actingAs($admin)
            ->test(ConfirmOrder::class, ['record' => $order->getKey()])
            ->assertActionExists('printInvoice');

        $order->confirm();
        $order->deliverAllRemaining();

        Livewire::actingAs($admin)
            ->test(DeliveredViewOrder::class, ['record' => $order->getKey()])
            ->assertActionExists('printInvoice');
    }

    public function test_admin_can_open_the_printable_order_document(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->publicOrder();

        $this->actingAs($admin)
            ->get($this->printUrl($order))
            ->assertOk()
            ->assertSee('فاتورة الطلب - مستند غير محاسبي')
            ->assertSee($order->order_number)
            ->assertSee('متجر الاختبار')
            ->assertSee('SNP-1')
            ->assertSee('Snapshot Shoe');
    }

    public function test_guest_cannot_open_the_printable_order_document(): void
    {
        [$order] = $this->publicOrder();

        $this->get($this->printUrl($order))->assertRedirect('/admin/login');
    }

    public function test_print_document_uses_order_item_snapshots(): void
    {
        $admin = User::factory()->create();
        [$order, , $product] = $this->publicOrder();

        $product->update(['name' => 'Changed Later', 'price' => 999]);

        $this->actingAs($admin)
            ->get($this->printUrl($order))
            ->assertOk()
            ->assertSee('Snapshot Shoe')
            ->assertDontSee('Changed Later');
    }

    public function test_print_document_shows_public_prices_and_totals(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->publicOrder(quantity: 3);

        $this->actingAs($admin)
            ->get($this->printUrl($order))
            ->assertOk()
            ->assertSee('450.00')
            ->assertSee('1,350.00')
            ->assertSee('إجمالي الطلب');
    }

    public function test_print_document_never_exposes_request_price_values(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->publicOrder(quantity: 3);
        $this->requestPriceItem($order, quantity: 2);

        $this->actingAs($admin)
            ->get($this->printUrl($order))
            ->assertOk()
            ->assertSee('إجمالي العناصر ذات السعر المعلن')
            ->assertSee('1,350.00')
            ->assertSee('لا يشمل المنتجات التي يتم تحديد سعرها بشكل منفصل.')
            ->assertSee('السعر عند الطلب')
            ->assertDontSee('9,876.54')
            ->assertDontSee('9876.54')
            ->assertDontSee('19,753.08')
            ->assertDontSee('إجمالي الطلب');
    }

    public function test_print_document_renders_no_monetary_total_when_all_items_are_request_price(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->publicOrder();
        $order->items()->delete();
        $this->requestPriceItem($order, quantity: 2);

        $this->actingAs($admin)
            ->get($this->printUrl($order))
            ->assertOk()
            ->assertSee('السعر عند الطلب')
            ->assertDontSee('إجمالي العناصر ذات السعر المعلن')
            ->assertDontSee('9876.54')
            ->assertSee('لا يشمل المنتجات التي يتم تحديد سعرها بشكل منفصل.');
    }

    public function test_print_document_uses_western_digits(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->publicOrder(quantity: 3);

        $this->actingAs($admin)
            ->get($this->printUrl($order))
            ->assertOk()
            ->assertSee('1,350.00')
            ->assertDontSee('١٬٣٥٠');
    }

    public function test_no_accounting_invoice_infrastructure_is_introduced(): void
    {
        $this->assertFalse(Schema::hasTable('invoices'));
        $this->assertFalse(class_exists(Invoice::class));
    }
}
