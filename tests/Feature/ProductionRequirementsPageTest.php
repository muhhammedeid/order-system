<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\ProductionRequirements;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionRequirementsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function newProduct(array $attributes = []): Product
    {
        return Product::factory()->create([
            'price_visibility' => 'public',
            'price' => 100,
            'active' => true,
            'size_enabled' => true,
            ...$attributes,
        ]);
    }

    private function newVariant(Product $product, string $color = 'Black', ?string $size = '41'): ProductVariant
    {
        return ProductVariant::factory()
            ->for($product)
            ->create([
                'color' => $color,
                'size' => $size,
                'available_quantity' => 0,
            ]);
    }

    private function newOrder(OrderStatus $status = OrderStatus::New, array $customerAttributes = []): Order
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create($customerAttributes)->id,
            'total_quantity' => 0,
        ]);

        if ($status !== OrderStatus::New && $status !== OrderStatus::Cancelled) {
            $order->confirm();
        }

        if ($status === OrderStatus::Cancelled) {
            $order->cancel();
        }

        return $order->refresh();
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function orderWithItem(OrderStatus $status, int $quantity, int $delivered = 0, ?Product $product = null, ?ProductVariant $variant = null, array $customerAttributes = []): array
    {
        $product ??= $this->newProduct();
        $variant ??= $this->newVariant($product);

        $order = $this->newOrder($status, $customerAttributes);

        $item = $order->items()->create(
            OrderItem::snapshotFromVariant($variant) + ['quantity' => $quantity]
        );

        $order->recalculateTotalQuantity();

        if ($delivered > 0) {
            $order->recordDeliveries([$item->id => ['quantity' => $delivered, 'expected_delivered' => 0]]);
        }

        return [$order->refresh(), $item->refresh()];
    }

    public function test_page_lists_only_confirmed_and_partially_delivered_outstanding_rows(): void
    {
        [$newOrder] = $this->orderWithItem(OrderStatus::New, 5);
        [$cancelledOrder] = $this->orderWithItem(OrderStatus::Cancelled, 5);
        [$deliveredOrder] = $this->orderWithItem(OrderStatus::Delivered, 5, 5);
        [$confirmedOrder] = $this->orderWithItem(OrderStatus::Confirmed, 5);
        [$partialOrder] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8, 3);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertCanSeeTableRecords([$confirmedOrder, $partialOrder])
            ->assertCanNotSeeTableRecords([$newOrder, $cancelledOrder, $deliveredOrder]);
    }

    public function test_page_excludes_items_with_zero_remaining_quantity(): void
    {
        $product = $this->newProduct();

        [$confirmedOrder, $item] = $this->orderWithItem(OrderStatus::Confirmed, 5, 0, $product, $this->newVariant($product, 'Black', '41'));
        [$partialOrder, $partialItem] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8, 3, $product, $this->newVariant($product, 'White', '42'));

        $confirmedOrder->recordDeliveries([$item->id => ['quantity' => 5, 'expected_delivered' => 0]]);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertCanSeeTableRecords([$partialOrder])
            ->assertCanNotSeeTableRecords([$confirmedOrder]);

        $this->assertSame(0, $item->refresh()->remaining_quantity);
        $this->assertSame(5, $partialItem->remaining_quantity);
    }

    public function test_row_exposes_order_customer_snapshot_and_remaining_columns(): void
    {
        $product = $this->newProduct(['name' => 'Runner', 'product_code' => 'RUN-1']);
        $variant = $this->newVariant($product, 'Blue', '43');

        [$order] = $this->orderWithItem(
            OrderStatus::PartiallyDelivered,
            9,
            4,
            $product,
            $variant,
            ['name' => 'أحمد', 'phone' => '01000000001'],
        );

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->assertCanSeeTableRecords([$order])
            ->assertSee('أحمد')
            ->assertSee('01000000001')
            ->assertSee('RUN-1')
            ->assertSee('Runner')
            ->assertSee('Blue')
            ->assertSee('43');
    }

    public function test_optional_size_renders_placeholder_for_unsized_products(): void
    {
        $product = $this->newProduct(['size_enabled' => false]);
        $variant = $this->newVariant($product, 'Black', null);

        [$order] = $this->orderWithItem(OrderStatus::Confirmed, 4, 0, $product, $variant);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertCanSeeTableRecords([$order]);
    }

    public function test_product_filter_restricts_rows_and_header_reconciles(): void
    {
        $productA = $this->newProduct(['name' => 'Shoe A', 'product_code' => 'SH-A']);
        $productB = $this->newProduct(['name' => 'Shoe B', 'product_code' => 'SH-B']);

        [$orderA1] = $this->orderWithItem(OrderStatus::Confirmed, 5, 0, $productA, $this->newVariant($productA, 'Black', '41'));
        [$orderA2] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8, 3, $productA, $this->newVariant($productA, 'White', '42'));
        [$orderB] = $this->orderWithItem(OrderStatus::Confirmed, 4, 0, $productB);

        $page = Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class, ['product' => $productA->id]);

        $page->assertCanSeeTableRecords([$orderA1, $orderA2])
            ->assertCanNotSeeTableRecords([$orderB]);

        $this->assertSame(10, $page->instance()->remainingTotal());
        $this->assertSame(4, OrderItem::productionRemainingQuantityTotal($productB->id));
        $this->assertSame(14, OrderItem::productionRemainingQuantityTotal());
    }

    public function test_drill_down_total_reconciles_with_card_and_outstanding_kpi(): void
    {
        $productA = $this->newProduct(['name' => 'Shoe A', 'product_code' => 'SH-A']);
        $productB = $this->newProduct(['name' => 'Shoe B', 'product_code' => 'SH-B']);

        $this->orderWithItem(OrderStatus::Confirmed, 5, 0, $productA, $this->newVariant($productA, 'Black', '41'));
        $this->orderWithItem(OrderStatus::PartiallyDelivered, 8, 3, $productA, $this->newVariant($productA, 'White', '42'));
        $this->orderWithItem(OrderStatus::Confirmed, 4, 0, $productB);

        $totals = OrderItem::productProductionTotals();

        // Card main quantity: the sum of the requested quantities per color.
        $cardA = (int) $totals->firstWhere('product_id', $productA->id)->required_quantity;
        $cardB = (int) $totals->firstWhere('product_id', $productB->id)->required_quantity;

        $this->assertSame(13, $cardA);
        $this->assertSame(4, $cardB);

        $pageA = Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class, ['product' => $productA->id]);

        // Header and KPI: the remaining quantity per color of the same rows.
        $this->assertSame(10, $pageA->instance()->remainingTotal());
        $this->assertSame(10, OrderItem::productionRemainingQuantityTotal($productA->id));

        $unfiltered = Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class);

        $this->assertSame(14, $unfiltered->instance()->remainingTotal());
        $this->assertSame(OrderItem::productionRemainingQuantityTotal(), $unfiltered->instance()->remainingTotal());
    }

    public function test_table_shows_per_color_required_delivered_and_remaining_values(): void
    {
        $product = $this->newProduct([
            'name' => 'Heel',
            'product_code' => 'MAI-001',
            'color_enabled' => false,
            'size_enabled' => false,
        ]);
        $variant = $this->newVariant($product, 'Black', '37');
        $this->newVariant($product, 'White', '37');
        $this->newVariant($product, 'Beige', '37');

        $order = $this->newOrder(OrderStatus::Confirmed);
        $snapshot = OrderItem::snapshotFromVariant($variant);
        $snapshot['color'] = 'Black، White، Beige';

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => 5,
            'color_count' => 3,
            'quantity' => 15,
        ]);

        $order->recalculateTotalQuantity();

        // 6 physical pieces = 2 per color.
        $order->recordDeliveries([$item->id => ['quantity' => 2, 'expected_delivered' => 0]]);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->assertCanSeeTableRecords([$order->refresh()])
            ->assertTableColumnStateSet('requested_quantity', 5, $item)
            ->assertTableColumnStateSet('delivered_quantity_per_color', 2, $item)
            ->assertTableColumnStateSet('remaining_quantity_per_color', 3, $item)
            ->assertSee('الكمية المطلوبة لكل لون')
            ->assertSee('تم تسليمه لكل لون')
            ->assertSee('المتبقي لكل لون');

        $this->assertSame(6, $item->refresh()->delivered_quantity);
        $this->assertSame(3, $item->remaining_quantity_per_color);
        $this->assertSame(3, $page->instance()->remainingTotal());
    }

    public function test_historical_single_color_items_keep_their_totals_as_per_color_values(): void
    {
        [, $item] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8, 3);

        $this->assertSame(8, $item->requested_quantity);
        $this->assertSame(1, $item->effectiveColorCount());
        $this->assertSame(8, $item->quantity);
        $this->assertSame(3, $item->delivered_quantity_per_color);
        $this->assertSame(5, $item->remaining_quantity_per_color);
        $this->assertSame(5, $item->remaining_quantity);
    }

    public function test_legacy_odd_delivered_remainders_reconcile_between_header_and_rows(): void
    {
        $product = $this->newProduct([
            'name' => 'Legacy Colors',
            'product_code' => 'LEG-1',
            'color_enabled' => false,
            'size_enabled' => false,
        ]);

        $variant = $this->newVariant($product, 'Black', '37');
        $this->newVariant($product, 'White', '37');
        $this->newVariant($product, 'Beige', '37');

        $order = $this->newOrder(OrderStatus::Confirmed);

        $snapshot = OrderItem::snapshotFromVariant($variant);
        $snapshot['color'] = 'Black، White، Beige';

        $threeColorItem = $order->items()->create($snapshot + [
            'requested_quantity' => 5,
            'color_count' => 3,
            'quantity' => 15,
        ]);

        $twoColorItem = $order->items()->create($snapshot + [
            'color' => 'Black، White',
            'requested_quantity' => 4,
            'color_count' => 2,
            'quantity' => 8,
        ]);

        $order->recalculateTotalQuantity();

        // Historical deliveries that are not multiples of the color count.
        $threeColorItem->setDeliveredQuantity(5);
        $twoColorItem->setDeliveredQuantity(1);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $threeColorItem->refresh();
        $twoColorItem->refresh();

        $this->assertSame(1, $threeColorItem->delivered_quantity_per_color);
        $this->assertSame(4, $threeColorItem->remaining_quantity_per_color);
        $this->assertSame(0, $twoColorItem->delivered_quantity_per_color);
        $this->assertSame(4, $twoColorItem->remaining_quantity_per_color);

        $expectedTotal = $threeColorItem->remaining_quantity_per_color + $twoColorItem->remaining_quantity_per_color;

        $this->assertSame($expectedTotal, $page->instance()->remainingTotal());
        $this->assertSame($expectedTotal, OrderItem::productionRemainingQuantityTotal());
    }

    public function test_cards_section_appears_above_the_requirements_table(): void
    {
        $this->orderWithItem(OrderStatus::Confirmed, 5);

        $html = $this->actingAs(User::factory()->create())
            ->get('/admin/production-requirements')
            ->assertOk()
            ->assertSee('توزيع الألوان المطلوبة')
            ->getContent();

        $cardsPosition = strpos($html, 'class="production-grid"');
        $tablePosition = strpos($html, 'fi-ta-ctn');

        $this->assertNotFalse($cardsPosition, 'Expected the requirement cards to be rendered.');
        $this->assertNotFalse($tablePosition, 'Expected the requirements table to be rendered.');
        $this->assertLessThan($tablePosition, $cardsPosition, 'The cards must appear before the table.');
    }

    public function test_page_cards_follow_the_product_filter(): void
    {
        $productA = $this->newProduct(['name' => 'Shoe A', 'product_code' => 'SH-A']);
        $productB = $this->newProduct(['name' => 'Shoe B', 'product_code' => 'SH-B']);

        $this->orderWithItem(OrderStatus::Confirmed, 5, 0, $productA, $this->newVariant($productA, 'Black', '41'));
        $this->orderWithItem(OrderStatus::Confirmed, 4, 0, $productB, $this->newVariant($productB, 'Red', '40'));

        $this->actingAs(User::factory()->create())
            ->get("/admin/production-requirements?product={$productA->id}")
            ->assertOk()
            ->assertSee('SH-A')
            ->assertSee('Black')
            ->assertDontSee('SH-B')
            ->assertDontSee('Red');
    }

    public function test_order_number_links_to_the_order_view_page(): void
    {
        [$order] = $this->orderWithItem(OrderStatus::Confirmed, 5);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->assertSee("/admin/order-management/{$order->id}", false);
    }

    public function test_search_matches_order_number_and_product_code(): void
    {
        $productA = $this->newProduct(['name' => 'Shoe A', 'product_code' => 'SH-A']);
        $productB = $this->newProduct(['name' => 'Shoe B', 'product_code' => 'SH-B']);

        [$orderA] = $this->orderWithItem(OrderStatus::Confirmed, 5, 0, $productA);
        [$orderB] = $this->orderWithItem(OrderStatus::Confirmed, 4, 0, $productB);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->searchTable('SH-A')
            ->assertCanSeeTableRecords([$orderA])
            ->assertCanNotSeeTableRecords([$orderB]);

        $page->searchTable($orderB->order_number)
            ->assertCanSeeTableRecords([$orderB])
            ->assertCanNotSeeTableRecords([$orderA]);
    }

    public function test_empty_state_when_nothing_is_outstanding(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertSee('لا توجد كميات مطلوبة للتشغيل حالياً');

        $this->assertSame(0, OrderItem::productionRemainingQuantityTotal());
    }

    public function test_page_renders_with_the_updated_terminology_and_western_digits(): void
    {
        $this->orderWithItem(OrderStatus::Confirmed, 1234);

        $this->actingAs(User::factory()->create())
            ->get('/admin/production-requirements')
            ->assertOk()
            ->assertSee('المطلوب للتشغيل')
            ->assertSee('1,234')
            ->assertDontSee('١٬٢٣٤');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/production-requirements')->assertRedirect('/admin/login');
    }

    public function test_admin_can_open_the_page_with_and_without_product_filter(): void
    {
        $product = $this->newProduct();

        $this->orderWithItem(OrderStatus::Confirmed, 5, 0, $product);

        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/production-requirements')->assertOk();
        $this->actingAs($admin)->get("/admin/production-requirements?product={$product->id}")->assertOk();
    }
}
