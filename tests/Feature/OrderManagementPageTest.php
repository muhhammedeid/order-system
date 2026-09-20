<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Filament\Resources\OrderManagement\Pages\EditOrder;
use App\Filament\Resources\OrderManagement\Pages\ListOrders;
use App\Filament\Resources\OrderManagement\Pages\ViewOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderManagementPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Order, 1: OrderItem, 2: ProductVariant}
     */
    private function makeOrder(OrderStatus $status = OrderStatus::New, int $quantity = 5): array
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
            OrderItem::snapshotFromVariant($variant) + ['quantity' => $quantity]
        );
        $order->recalculateTotalQuantity();

        if ($status !== OrderStatus::New && $status !== OrderStatus::Cancelled) {
            $order->confirm();
        }

        if ($status === OrderStatus::PartiallyDelivered) {
            $order->recordDeliveries([$item->id => ['quantity' => 2, 'expected_delivered' => 0]]);
        }

        if ($status === OrderStatus::Delivered) {
            $order->deliverAllRemaining();
        }

        if ($status === OrderStatus::Cancelled) {
            $order->cancel();
        }

        $order->refresh();

        return [$order, $item, $variant];
    }

    public function test_management_page_renders_status_tabs_with_trusted_counts(): void
    {
        $this->makeOrder(OrderStatus::New);
        $this->makeOrder(OrderStatus::Confirmed);
        $this->makeOrder(OrderStatus::Confirmed);
        $this->makeOrder(OrderStatus::PartiallyDelivered);
        $this->makeOrder(OrderStatus::Delivered);
        $this->makeOrder(OrderStatus::Cancelled);

        $page = new ListOrders;
        $tabs = $page->getTabs();

        $this->assertSame(['new', 'confirmed', 'partially_delivered', 'delivered', 'cancelled'], array_keys($tabs));
        $this->assertSame('1', $tabs['new']->getBadge());
        $this->assertSame('2', $tabs['confirmed']->getBadge());
        $this->assertSame('1', $tabs['partially_delivered']->getBadge());
        $this->assertSame('1', $tabs['delivered']->getBadge());
        $this->assertSame('1', $tabs['cancelled']->getBadge());

        Livewire::actingAs(User::factory()->create())
            ->test(ListOrders::class)
            ->assertSee('جديد')
            ->assertSee('مؤكد')
            ->assertSee('تم التسليم جزئيًا')
            ->assertSee('تم التسليم')
            ->assertSee('ملغي');
    }

    public function test_tabs_filter_records_by_status(): void
    {
        [$newOrder] = $this->makeOrder(OrderStatus::New);
        [$confirmedOrder] = $this->makeOrder(OrderStatus::Confirmed);

        Livewire::actingAs(User::factory()->create())
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$newOrder])
            ->assertCanNotSeeTableRecords([$confirmedOrder])
            ->set('activeTab', 'confirmed')
            ->assertCanSeeTableRecords([$confirmedOrder])
            ->assertCanNotSeeTableRecords([$newOrder]);
    }

    public function test_navigation_badge_shows_new_orders_count(): void
    {
        $this->makeOrder(OrderStatus::New);
        $this->makeOrder(OrderStatus::New);
        $this->makeOrder(OrderStatus::Confirmed);

        $this->assertSame('إدارة الطلبات', OrderManagementResource::getNavigationLabel());
        $this->assertSame('2', OrderManagementResource::getNavigationBadge());
    }

    public function test_table_actions_follow_the_status_matrix(): void
    {
        [$newOrder] = $this->makeOrder(OrderStatus::New);
        [$confirmedOrder] = $this->makeOrder(OrderStatus::Confirmed);
        [$partialOrder] = $this->makeOrder(OrderStatus::PartiallyDelivered);
        [$deliveredOrder] = $this->makeOrder(OrderStatus::Delivered);
        [$cancelledOrder] = $this->makeOrder(OrderStatus::Cancelled);

        $page = Livewire::actingAs(User::factory()->create())->test(ListOrders::class);

        $page->assertTableActionVisible('confirm', $newOrder)
            ->assertTableActionVisible('cancel', $newOrder)
            ->assertTableActionVisible('edit', $newOrder)
            ->assertTableActionVisible('review', $newOrder)
            ->assertTableActionHidden('recordDelivery', $newOrder)
            ->assertTableActionHidden('deliverAll', $newOrder);

        $page->set('activeTab', 'confirmed')
            ->assertTableActionHidden('confirm', $confirmedOrder)
            ->assertTableActionHidden('cancel', $confirmedOrder)
            ->assertTableActionHidden('edit', $confirmedOrder)
            ->assertTableActionVisible('recordDelivery', $confirmedOrder)
            ->assertTableActionVisible('deliverAll', $confirmedOrder);

        $page->set('activeTab', 'partially_delivered')
            ->assertTableActionHidden('confirm', $partialOrder)
            ->assertTableActionHidden('cancel', $partialOrder)
            ->assertTableActionHidden('edit', $partialOrder)
            ->assertTableActionVisible('recordDelivery', $partialOrder)
            ->assertTableActionVisible('deliverAll', $partialOrder);

        $page->set('activeTab', 'delivered')
            ->assertTableActionHidden('confirm', $deliveredOrder)
            ->assertTableActionHidden('cancel', $deliveredOrder)
            ->assertTableActionHidden('edit', $deliveredOrder)
            ->assertTableActionHidden('recordDelivery', $deliveredOrder)
            ->assertTableActionHidden('deliverAll', $deliveredOrder);

        $page->set('activeTab', 'cancelled')
            ->assertTableActionHidden('confirm', $cancelledOrder)
            ->assertTableActionHidden('cancel', $cancelledOrder)
            ->assertTableActionHidden('edit', $cancelledOrder)
            ->assertTableActionHidden('recordDelivery', $cancelledOrder)
            ->assertTableActionHidden('deliverAll', $cancelledOrder);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/order-management')->assertRedirect('/admin/login');
    }

    public function test_edit_page_is_denied_after_leaving_new(): void
    {
        $admin = User::factory()->create();

        [$newOrder] = $this->makeOrder(OrderStatus::New);
        [$confirmedOrder] = $this->makeOrder(OrderStatus::Confirmed);
        [$partialOrder] = $this->makeOrder(OrderStatus::PartiallyDelivered);
        [$deliveredOrder] = $this->makeOrder(OrderStatus::Delivered);
        [$cancelledOrder] = $this->makeOrder(OrderStatus::Cancelled);

        $this->actingAs($admin)->get("/admin/order-management/{$newOrder->getKey()}/edit")->assertOk();
        $this->actingAs($admin)->get("/admin/order-management/{$confirmedOrder->getKey()}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/admin/order-management/{$partialOrder->getKey()}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/admin/order-management/{$deliveredOrder->getKey()}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/admin/order-management/{$cancelledOrder->getKey()}/edit")->assertForbidden();
    }

    public function test_orders_without_items_render_aggregate_columns_safely(): void
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$order]);
    }

    public function test_admin_edits_items_notes_and_totals_from_the_page(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $variant] = $this->makeOrder(OrderStatus::New, 5);

        $newVariant = ProductVariant::factory()
            ->for(Product::factory()->create([
                'name' => 'Edited Shoe',
                'product_code' => 'ED-1',
                'size_enabled' => true,
                'price_visibility' => 'public',
                'price' => 250,
            ]))
            ->create(['color' => 'White', 'size' => '42', 'available_quantity' => 0]);

        Livewire::actingAs($admin)
            ->test(EditOrder::class, ['record' => $order->getKey()])
            ->fillForm([
                'admin_notes' => 'تم الاتفاق على الكميات',
                'items' => [
                    [
                        'id' => $item->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'quantity' => 9,
                    ],
                    [
                        'id' => null,
                        'product_id' => $newVariant->product_id,
                        'product_variant_id' => $newVariant->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();

        $this->assertSame(11, $order->total_quantity);
        $this->assertSame('تم الاتفاق على الكميات', $order->admin_notes);
        $this->assertSame(2, $order->items()->count());

        $editedItem = $order->items()->where('product_code', 'ED-1')->firstOrFail();

        $this->assertSame('Edited Shoe', $editedItem->product_name);
        $this->assertSame('White', $editedItem->color);
        $this->assertSame('42', $editedItem->size);
        $this->assertSame('250.00', $editedItem->unit_price);
        $this->assertSame(0, $editedItem->delivered_quantity);
    }

    public function test_item_repeater_renders_as_a_formatting_table(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->makeOrder(OrderStatus::New, 5);

        Livewire::actingAs($admin)
            ->test(EditOrder::class, ['record' => $order->getKey()])
            ->assertSeeHtml('fi-fo-table-repeater');
    }

    public function test_item_repeater_rejects_invalid_quantities(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $variant] = $this->makeOrder(OrderStatus::New, 5);

        Livewire::actingAs($admin)
            ->test(EditOrder::class, ['record' => $order->getKey()])
            ->fillForm([
                'items' => [
                    [
                        'id' => $item->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'quantity' => 0,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['items.0.quantity']);

        $this->assertSame(5, $item->refresh()->quantity);
    }

    public function test_order_quantities_render_with_western_digits(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->makeOrder(OrderStatus::New, 1234);

        $this->actingAs($admin)
            ->get('/admin/order-management')
            ->assertOk()
            ->assertSee('1,234')
            ->assertDontSee('١٬٢٣٤');

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}/confirm")
            ->assertOk()
            ->assertSee('1,234')
            ->assertDontSee('١٬٢٣٤');
    }

    public function test_record_delivery_action_updates_status_from_the_view_page(): void
    {
        $admin = User::factory()->create();
        [$order, $item] = $this->makeOrder(OrderStatus::Confirmed, 5);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('recordDelivery', data: [
                'deliveries' => [$item->id => 2],
                'expected' => [$item->id => 0],
            ])
            ->assertNotified();

        $order->refresh();

        $this->assertSame(OrderStatus::PartiallyDelivered, $order->status);
        $this->assertSame(2, $item->refresh()->delivered_quantity);
        $this->assertSame(3, $item->remaining_quantity);
    }

    public function test_deliver_all_action_completes_the_order_from_the_view_page(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->makeOrder(OrderStatus::Confirmed, 5);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('deliverAll')
            ->assertNotified();

        $order->refresh();

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(0, $order->items()->first()->remaining_quantity);
    }

    public function test_stale_delivery_submission_shows_a_danger_notification(): void
    {
        $admin = User::factory()->create();
        [$order, $item] = $this->makeOrder(OrderStatus::Confirmed, 5);

        $order->recordDeliveries([$item->id => ['quantity' => 1, 'expected_delivered' => 0]]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('recordDelivery', data: [
                'deliveries' => [$item->id => 1],
                'expected' => [$item->id => 0],
            ])
            ->assertNotified();

        $this->assertSame(1, $item->refresh()->delivered_quantity);
    }

    public function test_actions_follow_status_on_the_view_page(): void
    {
        $admin = User::factory()->create();

        [$newOrder] = $this->makeOrder(OrderStatus::New);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $newOrder->getKey()])
            ->assertActionVisible('confirm')
            ->assertActionVisible('cancel')
            ->assertActionVisible('edit')
            ->assertActionHidden('recordDelivery')
            ->assertActionHidden('deliverAll');

        [$deliveredOrder] = $this->makeOrder(OrderStatus::Delivered);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $deliveredOrder->getKey()])
            ->assertActionHidden('confirm')
            ->assertActionHidden('cancel')
            ->assertActionHidden('edit')
            ->assertActionHidden('recordDelivery')
            ->assertActionHidden('deliverAll');

        [$cancelledOrder] = $this->makeOrder(OrderStatus::Cancelled);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $cancelledOrder->getKey()])
            ->assertActionHidden('confirm')
            ->assertActionHidden('cancel')
            ->assertActionHidden('edit')
            ->assertActionHidden('recordDelivery')
            ->assertActionHidden('deliverAll');
    }
}
