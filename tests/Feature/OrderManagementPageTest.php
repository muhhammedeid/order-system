<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Filament\Resources\OrderManagement\Pages\EditOrder;
use App\Filament\Resources\OrderManagement\Pages\ListOrders;
use App\Filament\Resources\OrderManagement\Pages\ViewOrder;
use App\Filament\Widgets\ProductionRequirementsWidget;
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
            $order->recordDeliveries([
                $item->colorQuantities()->firstOrFail()->id => ['quantity' => 2, 'expected_delivered' => 0],
            ]);
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
            ->assertTableActionVisible('edit', $confirmedOrder)
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
        $this->actingAs($admin)->get("/admin/order-management/{$confirmedOrder->getKey()}/edit")->assertOk();
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
                        'requested_quantity' => 9,
                    ],
                    [
                        'id' => null,
                        'product_id' => $newVariant->product_id,
                        'product_variant_id' => $newVariant->id,
                        'requested_quantity' => 2,
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
                        'requested_quantity' => 0,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['items.0.requested_quantity']);

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

    /**
     * @return array{0: Order, 1: OrderItem, 2: array<string, OrderItemColorQuantity>}
     */
    private function multiColorOrder(OrderStatus $status = OrderStatus::Confirmed): array
    {
        $product = Product::factory()->create([
            'color_enabled' => false,
            'size_enabled' => false,
            'price_visibility' => 'public',
            'price' => 100,
        ]);

        $variants = collect(['Black', 'White', 'Beige'])->map(fn (string $color): ProductVariant => ProductVariant::factory()
            ->for($product)
            ->create(['color' => $color, 'size' => '37', 'available_quantity' => 0]));

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        $snapshot = OrderItem::snapshotFromVariant($variants->first());
        $snapshot['color'] = 'Black، White، Beige';

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => 5,
            'color_count' => 3,
            'quantity' => 15,
        ]);

        $order->recalculateTotalQuantity();

        if ($status !== OrderStatus::New) {
            $order->confirm();
        }

        $order->refresh();

        return [
            $order,
            $item->refresh(),
            $item->colorQuantities->keyBy('color')->all(),
        ];
    }

    public function test_record_delivery_modal_lists_one_row_per_ordered_color(): void
    {
        $admin = User::factory()->create();
        [$order, $item] = $this->multiColorOrder();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->mountAction('recordDelivery')
            ->assertMountedActionModalSee('Black')
            ->assertMountedActionModalSee('White')
            ->assertMountedActionModalSee('Beige');
    }

    public function test_record_delivery_action_submits_one_quantity_per_color(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('recordDelivery', data: [
                'deliveries' => [
                    $colors['Black']->id => 5,
                    $colors['White']->id => 2,
                    $colors['Beige']->id => 0,
                ],
                'expected' => [
                    $colors['Black']->id => 0,
                    $colors['White']->id => 0,
                    $colors['Beige']->id => 0,
                ],
            ])
            ->assertNotified();

        $this->assertSame(5, $colors['Black']->refresh()->delivered_quantity);
        $this->assertSame(0, $colors['Black']->remaining_quantity);
        $this->assertSame(2, $colors['White']->refresh()->delivered_quantity);
        $this->assertSame(3, $colors['White']->remaining_quantity);
        $this->assertSame(0, $colors['Beige']->refresh()->delivered_quantity);
        $this->assertSame(7, $item->refresh()->delivered_quantity);
        $this->assertSame(OrderStatus::PartiallyDelivered, $order->refresh()->status);
    }

    public function test_reconcile_deliveries_action_allocates_legacy_unallocated_pieces(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        $item->forceFill([
            'delivered_quantity' => 4,
            'unallocated_delivered_quantity' => 4,
        ])->save();
        $item->refresh();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible('reconcileDeliveries')
            ->callAction('reconcileDeliveries', data: [
                'allocations' => [
                    $colors['Black']->id => 3,
                    $colors['White']->id => 1,
                ],
            ])
            ->assertNotified();

        $this->assertSame(3, $colors['Black']->refresh()->delivered_quantity);
        $this->assertSame(1, $colors['White']->refresh()->delivered_quantity);
        $this->assertSame(0, (int) $item->refresh()->unallocated_delivered_quantity);
        $this->assertSame(4, (int) $item->delivered_quantity);
        $this->assertFalse($item->hasUnallocatedDeliveries());
    }

    public function test_reconcile_action_is_hidden_without_unallocated_deliveries(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->multiColorOrder();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionHidden('reconcileDeliveries');
    }

    public function test_partial_delivery_modal_lists_only_outstanding_snapshot_colors(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        // Black is fully delivered; White and Beige still require delivery.
        $colors['Black']->setDeliveredQuantity(5);
        $order->refreshDeliveryStatus();

        $component = Livewire::actingAs($admin)->test(ViewOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([
            $colors['White']->id => 0,
            $colors['Beige']->id => 0,
        ], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);

        $component->assertMountedActionModalSee('White')
            ->assertMountedActionModalSee('Beige')
            ->assertMountedActionModalSee('المطلوب لهذا اللون: 5')
            ->assertMountedActionModalSee('تم تسليمه من هذا اللون: 0')
            ->assertMountedActionModalSee('المتبقي من هذا اللون: 5');
    }

    public function test_partial_delivery_modal_ignores_product_colors_added_after_the_order(): void
    {
        $admin = User::factory()->create();

        $product = Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 100,
            'size_enabled' => true,
        ]);

        $black = ProductVariant::factory()
            ->for($product)
            ->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 0]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        $item = $order->items()->create(
            OrderItem::snapshotFromVariant($black) + ['quantity' => 5]
        );

        $order->recalculateTotalQuantity();
        $order->confirm();
        $order->refresh();

        // A color added to the product after the order was submitted.
        ProductVariant::factory()
            ->for($product)
            ->create(['color' => 'Beige', 'size' => '41', 'available_quantity' => 0]);

        $component = Livewire::actingAs($admin)->test(ViewOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([
            $item->refresh()->colorQuantities()->firstOrFail()->id => 0,
        ], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);

        $component->assertMountedActionModalDontSee('Beige');
    }

    public function test_fully_delivered_items_disappear_from_the_partial_delivery_modal(): void
    {
        $admin = User::factory()->create();
        [$order, $deliveredItem, $deliveredColors] = $this->multiColorOrder();

        foreach ($deliveredColors as $colorRow) {
            $colorRow->setDeliveredQuantity($colorRow->requested_quantity);
        }

        $pendingVariant = ProductVariant::factory()
            ->for(Product::factory()->create([
                'price_visibility' => 'public',
                'price' => 100,
                'size_enabled' => true,
            ]))
            ->create(['color' => 'Brown', 'size' => '41', 'available_quantity' => 0]);

        $pendingItem = $order->items()->create(
            OrderItem::snapshotFromVariant($pendingVariant) + ['quantity' => 4]
        );

        $order->recalculateTotalQuantity();
        $order->refreshDeliveryStatus();

        $component = Livewire::actingAs($admin)->test(ViewOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([
            $pendingItem->refresh()->colorQuantities()->firstOrFail()->id => 0,
        ], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);

        $component->assertMountedActionModalSee('Brown')
            ->assertMountedActionModalDontSee($deliveredItem->product_name);
    }

    public function test_partial_delivery_action_is_unavailable_when_no_color_remains(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible('recordDelivery');

        foreach ($colors as $colorRow) {
            $colorRow->setDeliveredQuantity($colorRow->requested_quantity);
        }

        $order->refreshDeliveryStatus();

        $this->assertFalse($order->refresh()->hasOutstandingColors());

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionHidden('recordDelivery')
            ->assertActionHidden('deliverAll');
    }

    public function test_order_view_shows_remaining_quantities_grouped_by_color(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        $colors['Black']->setDeliveredQuantity(5);
        $order->refreshDeliveryStatus();

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->assertSee('المتبقي حسب اللون')
            ->assertSee('White — 5 قطعة')
            ->assertSee('Beige — 5 قطعة')
            ->assertSee('ألوان مكتملة التسليم')
            ->assertSee('Black — تم التسليم بالكامل');
    }

    public function test_order_view_states_when_every_color_is_delivered(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        foreach ($colors as $colorRow) {
            $colorRow->setDeliveredQuantity($colorRow->requested_quantity);
        }

        $order->refreshDeliveryStatus();

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->assertSee('تم تسليم جميع الألوان')
            ->assertDontSee('المتبقي حسب اللون');
    }

    public function test_order_view_warns_about_unallocated_legacy_deliveries(): void
    {
        $admin = User::factory()->create();
        [$order, $item] = $this->multiColorOrder();

        $item->forceFill([
            'delivered_quantity' => 2,
            'unallocated_delivered_quantity' => 2,
        ])->save();

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->assertSee('كمية مسلمة قديمة تحتاج إلى توزيع على الألوان');
    }

    public function test_partial_delivery_modal_blocks_unallocated_items_until_reconciled(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        $item->forceFill([
            'delivered_quantity' => 2,
            'unallocated_delivered_quantity' => 2,
        ])->save();
        $item->refresh();

        $component = Livewire::actingAs($admin)->test(ViewOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);
        $component->assertMountedActionModalSee('كمية مسلمة قديمة تحتاج إلى توزيع على الألوان: 2');

        // After the reconciliation, only the colors with a positive
        // remaining quantity are offered again.
        $order->reconcileUnallocatedDeliveries([$colors['Black']->id => 2]);

        $component = Livewire::actingAs($admin)->test(ViewOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([
            $colors['Black']->id => 0,
            $colors['White']->id => 0,
            $colors['Beige']->id => 0,
        ], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);
    }

    public function test_item_card_never_exposes_a_bare_aggregate_remaining_entry(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        $colors['Black']->setDeliveredQuantity(5);
        $order->refreshDeliveryStatus();

        $html = $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->getContent();

        preg_match_all('/fi-in-entry-label" role="term">\s*([^<]+?)\s*<\/div>/u', $html, $matches);

        $labels = array_map('trim', $matches[1]);

        $this->assertNotEmpty($labels, 'Expected the item cards to render labelled entries.');
        $this->assertContains('المتبقي حسب اللون', $labels);
        $this->assertNotContains('المتبقي', $labels, 'A bare aggregate remaining value must not be displayed.');
        $this->assertNotContains('تم تسليمه', $labels, 'A bare aggregate delivered value must not be displayed.');
    }

    public function test_confirmation_view_shows_remaining_quantities_grouped_by_color(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder(OrderStatus::New);

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}/confirm")
            ->assertOk()
            ->assertSee('المتبقي حسب اللون')
            ->assertSee('Black — 5 قطعة')
            ->assertSee('White — 5 قطعة')
            ->assertSee('Beige — 5 قطعة')
            ->assertDontSee('ألوان مكتملة التسليم');
    }

    public function test_delivered_order_view_shows_all_colors_delivered(): void
    {
        $admin = User::factory()->create();
        [$order, $item, $colors] = $this->multiColorOrder();

        $order->deliverAllRemaining();

        $this->actingAs($admin)
            ->get("/admin/orders/{$order->getKey()}")
            ->assertOk()
            ->assertSee('تم تسليم جميع الألوان')
            ->assertSee('ألوان مكتملة التسليم')
            ->assertSee('Black — تم التسليم بالكامل')
            ->assertDontSee('المتبقي حسب اللون');
    }

    public function test_partial_delivery_visual_acceptance_scenario(): void
    {
        $admin = User::factory()->create();

        $product = Product::factory()->create([
            'name' => 'ZARA Heel',
            'product_code' => 'mai_002',
            'color_enabled' => false,
            'size_enabled' => false,
            'price_visibility' => 'public',
            'price' => 100,
        ]);

        $variants = collect(['Black', 'White'])->map(fn (string $color): ProductVariant => ProductVariant::factory()
            ->for($product)
            ->create(['color' => $color, 'size' => '37', 'available_quantity' => 0]));

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        $snapshot = OrderItem::snapshotFromVariant($variants->first());
        $snapshot['color'] = 'Black، White';

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => 10,
            'color_count' => 2,
            'quantity' => 20,
        ]);

        $order->recalculateTotalQuantity();
        $order->confirm();
        $order->refresh();

        $black = $item->refresh()->colorQuantities()->where('color', 'Black')->firstOrFail();
        $white = $item->colorQuantities()->where('color', 'White')->firstOrFail();

        // 5 pieces delivered from each color.
        $order->recordDeliveries([
            $black->id => ['quantity' => 5, 'expected_delivered' => 0],
            $white->id => ['quantity' => 5, 'expected_delivered' => 0],
        ]);

        // Modal: both colors, with their explicit per-color values.
        $component = Livewire::actingAs($admin)->test(ViewOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([$black->id => 0, $white->id => 0], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);
        $component->assertMountedActionModalSee('ZARA Heel — mai_002 — Black')
            ->assertMountedActionModalSee('ZARA Heel — mai_002 — White')
            ->assertMountedActionModalSee('المطلوب لهذا اللون: 10')
            ->assertMountedActionModalSee('تم تسليمه من هذا اللون: 5')
            ->assertMountedActionModalSee('المتبقي من هذا اللون: 5');

        // Order details: remaining grouped by color.
        $this->actingAs($admin)->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->assertSee('المتبقي حسب اللون')
            ->assertSee('Black — 5 قطعة')
            ->assertSee('White — 5 قطعة');

        // Production Requirements cards: the remaining quantities per color.
        $this->assertSame([
            ['color' => 'Black', 'remaining' => 5],
            ['color' => 'White', 'remaining' => 5],
        ], ProductionRequirementsWidget::requirementsFor()->first()['pending_colors']);

        // Deliver the remaining 5 of Black only.
        $order->recordDeliveries([$black->id => ['quantity' => 5, 'expected_delivered' => 5]]);

        $component = Livewire::actingAs($admin)->test(ViewOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([$white->id => 0], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);
        $component->assertMountedActionModalDontSee('Black');

        $this->actingAs($admin)->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->assertSee('White — 5 قطعة')
            ->assertSee('ألوان مكتملة التسليم')
            ->assertSee('Black — تم التسليم بالكامل');

        $this->assertSame([
            ['color' => 'White', 'remaining' => 5],
        ], ProductionRequirementsWidget::requirementsFor()->first()['pending_colors']);

        // Deliver the remaining 5 of White.
        $order->recordDeliveries([$white->id => ['quantity' => 5, 'expected_delivered' => 5]]);

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertTrue(ProductionRequirementsWidget::requirementsFor()->isEmpty());

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionHidden('recordDelivery')
            ->assertActionHidden('deliverAll');

        $this->actingAs($admin)->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->assertSee('تم تسليم جميع الألوان')
            ->assertDontSee('المتبقي حسب اللون');
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

        $colorRow = $item->colorQuantities()->firstOrFail();

        $order->recordDeliveries([$colorRow->id => ['quantity' => 1, 'expected_delivered' => 0]]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('recordDelivery', data: [
                'deliveries' => [$colorRow->id => 1],
                'expected' => [$colorRow->id => 0],
            ])
            ->assertNotified();

        $this->assertSame(1, $colorRow->refresh()->delivered_quantity);
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
