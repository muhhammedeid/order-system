<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveredOrdersHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(OrderStatus $status): Order
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create([
                'size_enabled' => true,
                'price_visibility' => 'public',
                'price' => 100,
            ]))
            ->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 0]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        $item = $order->items()->create(
            OrderItem::snapshotFromVariant($variant) + ['quantity' => 4]
        );
        $order->recalculateTotalQuantity();

        if ($status !== OrderStatus::New && $status !== OrderStatus::Cancelled) {
            $order->confirm();
        }

        if ($status === OrderStatus::PartiallyDelivered) {
            $order->recordDeliveries([$item->id => ['quantity' => 1, 'expected_delivered' => 0]]);
        }

        if ($status === OrderStatus::Delivered) {
            $order->deliverAllRemaining();
        }

        if ($status === OrderStatus::Cancelled) {
            $order->cancel();
        }

        return $order->refresh();
    }

    public function test_history_lists_only_delivered_orders(): void
    {
        $delivered = $this->makeOrder(OrderStatus::Delivered);
        $new = $this->makeOrder(OrderStatus::New);
        $confirmed = $this->makeOrder(OrderStatus::Confirmed);
        $partial = $this->makeOrder(OrderStatus::PartiallyDelivered);
        $cancelled = $this->makeOrder(OrderStatus::Cancelled);

        Livewire::actingAs(User::factory()->create())
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$delivered])
            ->assertCanNotSeeTableRecords([$new, $confirmed, $partial, $cancelled])
            ->assertSee('آخر تحديث');
    }

    public function test_direct_view_of_non_delivered_orders_is_denied(): void
    {
        $admin = User::factory()->create();

        foreach ([OrderStatus::New, OrderStatus::Confirmed, OrderStatus::PartiallyDelivered, OrderStatus::Cancelled] as $status) {
            $order = $this->makeOrder($status);

            $this->actingAs($admin)
                ->get("/admin/orders/{$order->getKey()}")
                ->assertNotFound();
        }

        $delivered = $this->makeOrder(OrderStatus::Delivered);

        $this->actingAs($admin)
            ->get("/admin/orders/{$delivered->getKey()}")
            ->assertOk();
    }

    public function test_history_view_is_read_only(): void
    {
        $admin = User::factory()->create();
        $delivered = $this->makeOrder(OrderStatus::Delivered);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $delivered->getKey()])
            ->assertActionDoesNotExist('confirm')
            ->assertActionDoesNotExist('cancel')
            ->assertActionDoesNotExist('recordDelivery')
            ->assertActionDoesNotExist('deliverAll')
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('review');

        $this->actingAs($admin)
            ->get("/admin/orders/{$delivered->getKey()}/edit")
            ->assertNotFound();
    }

    public function test_history_navigation_is_labeled_for_delivered_orders(): void
    {
        $this->assertSame('الطلبات المُسلَّمة', OrderResource::getNavigationLabel());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/orders')->assertRedirect('/admin/login');
    }
}
