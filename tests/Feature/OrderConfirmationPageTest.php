<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderManagement\Pages\ConfirmOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderConfirmationPageTest extends TestCase
{
    use RefreshDatabase;

    private function submitOrder(int $quantity = 3): Order
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create([
                'price_visibility' => 'public',
                'price' => 450,
                'size_enabled' => true,
            ]))
            ->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => $quantity]);
        $this->post('/checkout', ['name' => 'Test Store', 'phone' => '01001234567']);

        return Order::query()->firstOrFail();
    }

    private function variantOf(Order $order): ProductVariant
    {
        return $order->items->first()->productVariant;
    }

    public function test_confirmation_page_renders_for_a_new_order(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder();
        $item = $order->items->first();

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}/confirm")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('مراجعة وتأكيد')
            ->assertSee('الطلب في انتظار المراجعة')
            ->assertSee('بنود الطلب')
            ->assertSee($item->product_code)
            ->assertSee('بيانات العميل');
    }

    public function test_review_page_uses_two_columns_with_customer_information_before_items(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder();

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}/confirm")
            ->assertOk()
            ->assertSee('--cols-lg: repeat(12', false)
            ->assertSee('--col-span-lg: span 5', false)
            ->assertSee('--col-span-lg: span 7', false)
            ->assertSeeInOrder(['إجمالي القطع', 'رقم الموبايل', 'كود المنتج']);
    }

    public function test_admin_confirms_order_saving_status_and_notes_without_stock_change(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder(quantity: 3);
        $variant = $this->variantOf($order);

        Livewire::actingAs($admin)
            ->test(ConfirmOrder::class, ['record' => $order->getKey()])
            ->callAction('confirm', data: ['admin_notes' => 'تم التأكيد هاتفيًا مع العميل'])
            ->assertNotified()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'status' => OrderStatus::Confirmed->value,
            'admin_notes' => 'تم التأكيد هاتفيًا مع العميل',
        ]);

        $this->assertSame(10, $variant->refresh()->available_quantity);
    }

    public function test_confirm_and_cancel_actions_follow_the_approved_transitions(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder();

        $page = Livewire::actingAs($admin)->test(ConfirmOrder::class, ['record' => $order->getKey()]);

        $page->assertActionVisible('confirm')
            ->assertActionVisible('cancel');

        $page->callAction('confirm')->assertNotified();

        $page->assertActionHidden('confirm')
            ->assertActionHidden('cancel');
    }

    public function test_confirmation_succeeds_even_when_stock_is_zero(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder();
        $variant = $this->variantOf($order);

        $variant->update(['available_quantity' => 0]);

        Livewire::actingAs($admin)
            ->test(ConfirmOrder::class, ['record' => $order->getKey()])
            ->callAction('confirm', data: ['admin_notes' => 'تم التأكيد'])
            ->assertNotified();

        $this->assertSame(OrderStatus::Confirmed->value, $order->refresh()->status->value);
        $this->assertSame('تم التأكيد', $order->admin_notes);
        $this->assertSame(0, $variant->refresh()->available_quantity);
    }

    public function test_cancelling_a_new_order_from_the_page_has_no_stock_effect(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder(quantity: 4);
        $variant = $this->variantOf($order);

        Livewire::actingAs($admin)
            ->test(ConfirmOrder::class, ['record' => $order->getKey()])
            ->callAction('cancel')
            ->assertNotified();

        $this->assertSame(OrderStatus::Cancelled->value, $order->refresh()->status->value);
        $this->assertSame(10, $variant->refresh()->available_quantity);
    }

    public function test_confirmed_order_has_no_cancel_action(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder();

        $order->confirm();

        Livewire::actingAs($admin)
            ->test(ConfirmOrder::class, ['record' => $order->getKey()])
            ->assertActionHidden('confirm')
            ->assertActionHidden('cancel');
    }

    public function test_orders_table_links_new_orders_to_the_confirmation_page(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder();

        $this->actingAs($admin)
            ->get('/admin/order-management')
            ->assertOk()
            ->assertSee("/admin/order-management/{$order->getKey()}/confirm", false);
    }
}
