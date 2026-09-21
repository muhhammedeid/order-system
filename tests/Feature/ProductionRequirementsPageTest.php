<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\ProductionRequirements;
use App\Filament\Widgets\ProductionRequirementsWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemColorQuantity;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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
    private function orderWithItem(OrderStatus $status, int $quantity, ?Product $product = null, ?ProductVariant $variant = null, array $customerAttributes = []): array
    {
        $product ??= $this->newProduct();
        $variant ??= $this->newVariant($product);

        $order = $this->newOrder($status, $customerAttributes);

        $item = $order->items()->create(
            OrderItem::snapshotFromVariant($variant) + ['quantity' => $quantity]
        );

        $order->recalculateTotalQuantity();

        return [$order->refresh(), $item->refresh()];
    }

    /**
     * Confirmed order with one line covering several snapshot colors.
     *
     * @param  array<int, string>  $colors
     * @return array{0: Order, 1: OrderItem}
     */
    private function multiColorOrder(array $colors = ['Black', 'White'], int $requestedPerColor = 10): array
    {
        $product = $this->newProduct([
            'color_enabled' => false,
            'size_enabled' => false,
        ]);

        $variants = collect($colors)->map(fn (string $color): ProductVariant => $this->newVariant($product, $color, '37'));

        $order = $this->newOrder(OrderStatus::Confirmed);

        $snapshot = OrderItem::snapshotFromVariant($variants->first());
        $snapshot['color'] = implode('، ', $colors);

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => $requestedPerColor,
            'color_count' => count($colors),
            'quantity' => $requestedPerColor * count($colors),
        ]);

        $order->recalculateTotalQuantity();

        return [$order->refresh(), $item->refresh()];
    }

    private function colorRow(OrderItem $item, string $color): OrderItemColorQuantity
    {
        return $item->colorQuantities()->where('color', $color)->firstOrFail();
    }

    private function allColorRows(): Collection
    {
        return OrderItemColorQuantity::query()->orderBy('id')->get();
    }

    public function test_page_lists_only_outstanding_colors_of_production_orders(): void
    {
        [, $newItem] = $this->orderWithItem(OrderStatus::New, 5);
        [, $cancelledItem] = $this->orderWithItem(OrderStatus::Cancelled, 5);
        [$deliveredOrder, $deliveredItem] = $this->orderWithItem(OrderStatus::Delivered, 5);

        $deliveredOrder->deliverAllRemaining();

        [, $confirmedItem] = $this->orderWithItem(OrderStatus::Confirmed, 5);
        [, $partialItem] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8);

        $partialItem->colorQuantities()->firstOrFail()->setDeliveredQuantity(3);
        $partialItem->refresh();

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertCanSeeTableRecords([
                $this->colorRow($confirmedItem, 'Black'),
                $this->colorRow($partialItem, 'Black'),
            ])
            ->assertCanNotSeeTableRecords([
                $this->colorRow($newItem, 'Black'),
                $this->colorRow($cancelledItem, 'Black'),
                $this->colorRow($deliveredItem, 'Black'),
            ]);
    }

    public function test_page_excludes_colors_with_zero_remaining_quantity(): void
    {
        [$order, $item] = $this->multiColorOrder();

        $black = $this->colorRow($item, 'Black');
        $white = $this->colorRow($item, 'White');

        $black->setDeliveredQuantity(10);
        $white->setDeliveredQuantity(5);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertCanSeeTableRecords([$white->refresh()])
            ->assertCanNotSeeTableRecords([$black->refresh()])
            ->assertSee('White')
            ->assertDontSee('Black');
    }

    public function test_row_exposes_order_customer_snapshot_color_and_size_columns(): void
    {
        $product = $this->newProduct(['name' => 'Runner', 'product_code' => 'RUN-1']);
        $variant = $this->newVariant($product, 'Blue', '43');

        [$order, $item] = $this->orderWithItem(
            OrderStatus::Confirmed,
            9,
            $product,
            $variant,
            ['name' => 'أحمد', 'phone' => '01000000001'],
        );

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->assertCanSeeTableRecords([$this->colorRow($item, 'Blue')])
            ->assertSee('أحمد')
            ->assertSee('01000000001')
            ->assertSee('RUN-1')
            ->assertSee('Runner')
            ->assertSee('Blue')
            ->assertSee('43');
    }

    public function test_table_shows_requested_delivered_and_remaining_per_color(): void
    {
        [$order, $item] = $this->multiColorOrder(requestedPerColor: 10);

        $black = $this->colorRow($item, 'Black');
        $white = $this->colorRow($item, 'White');

        $black->setDeliveredQuantity(10);
        $white->setDeliveredQuantity(5);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->assertTableColumnStateSet('requested_quantity', 10, $white->refresh())
            ->assertTableColumnStateSet('delivered_quantity', 5, $white)
            ->assertTableColumnStateSet('remaining_quantity', 5, $white)
            ->assertSee('المطلوب لهذا اللون')
            ->assertSee('تم تسليمه لهذا اللون')
            ->assertSee('المتبقي لهذا اللون');

        $this->assertSame(1, $page->instance()->pendingColorsCount());
        $this->assertSame(15, $item->refresh()->delivered_quantity);
        $this->assertSame(5, $item->remaining_quantity);
    }

    public function test_optional_size_renders_placeholder_for_unsized_products(): void
    {
        $product = $this->newProduct(['size_enabled' => false]);
        $variant = $this->newVariant($product, 'Black', null);

        [, $item] = $this->orderWithItem(OrderStatus::Confirmed, 4, $product, $variant);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertCanSeeTableRecords([$this->colorRow($item, 'Black')]);
    }

    public function test_summary_counts_pending_colors_without_summing_quantities(): void
    {
        [$order, $item] = $this->multiColorOrder(['Black', 'White', 'Beige'], requestedPerColor: 10);

        $this->colorRow($item, 'Black')->setDeliveredQuantity(10);
        $this->colorRow($item, 'White')->setDeliveredQuantity(4);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        // Two colors still require production: White (6) and Beige (10).
        $this->assertSame(2, $page->instance()->pendingColorsCount());

        $page->assertSee('ألوان لها كميات متبقية للتشغيل: 2');
    }

    public function test_product_filter_restricts_rows_and_summary(): void
    {
        $productA = $this->newProduct(['name' => 'Shoe A', 'product_code' => 'SH-A']);
        $productB = $this->newProduct(['name' => 'Shoe B', 'product_code' => 'SH-B']);

        [, $itemA] = $this->orderWithItem(OrderStatus::Confirmed, 5, $productA, $this->newVariant($productA, 'Black', '41'));
        [, $itemB] = $this->orderWithItem(OrderStatus::Confirmed, 4, $productB, $this->newVariant($productB, 'Red', '40'));

        $page = Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class, ['product' => $productA->id]);

        $page->assertCanSeeTableRecords([$this->colorRow($itemA, 'Black')])
            ->assertCanNotSeeTableRecords([$this->colorRow($itemB, 'Red')]);

        $this->assertSame(1, $page->instance()->pendingColorsCount());
        $this->assertSame(2, OrderItemColorQuantity::outstandingColorCount());
    }

    public function test_cards_section_appears_above_the_requirements_table(): void
    {
        $this->orderWithItem(OrderStatus::Confirmed, 5);

        $html = $this->actingAs(User::factory()->create())
            ->get('/admin/production-requirements')
            ->assertOk()
            ->assertSee('المتبقي للتشغيل حسب اللون')
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

        $this->orderWithItem(OrderStatus::Confirmed, 5, $productA, $this->newVariant($productA, 'Black', '41'));
        $this->orderWithItem(OrderStatus::Confirmed, 4, $productB, $this->newVariant($productB, 'Red', '40'));

        $this->actingAs(User::factory()->create())
            ->get("/admin/production-requirements?product={$productA->id}")
            ->assertOk()
            ->assertSee('SH-A')
            ->assertSee('Black')
            ->assertDontSee('SH-B')
            ->assertDontSee('Red');
    }

    public function test_page_uses_snapshot_colors_after_product_colors_change(): void
    {
        $product = $this->newProduct([
            'color_enabled' => false,
            'size_enabled' => false,
        ]);

        $black = $this->newVariant($product, 'Black', '37');
        $this->newVariant($product, 'White', '37');

        $order = $this->newOrder(OrderStatus::Confirmed);

        $snapshot = OrderItem::snapshotFromVariant($black);
        $snapshot['color'] = 'Black، White';

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => 10,
            'color_count' => 2,
            'quantity' => 20,
        ]);

        $order->recalculateTotalQuantity();

        // The product later gains a color and loses another one.
        $this->newVariant($product, 'Beige', '37');
        $product->variants()->where('color', 'Black')->delete();

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->assertCanSeeTableRecords([
            $this->colorRow($item, 'Black'),
            $this->colorRow($item, 'White'),
        ])
            ->assertSee('Black')
            ->assertSee('White')
            ->assertDontSee('Beige');

        $this->actingAs(User::factory()->create())
            ->get('/admin/production-requirements')
            ->assertOk()
            ->assertSee('Black')
            ->assertSee('White')
            ->assertDontSee('Beige');
    }

    public function test_historical_single_color_items_keep_their_totals_as_per_color_values(): void
    {
        [, $item] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8);
        $item->refresh();

        $colorRow = $this->colorRow($item, 'Black');
        $colorRow->setDeliveredQuantity(3);

        $this->assertSame(8, $colorRow->refresh()->requested_quantity);
        $this->assertSame(3, $colorRow->delivered_quantity);
        $this->assertSame(5, $colorRow->remaining_quantity);
        $this->assertSame(3, $item->refresh()->delivered_quantity);
        $this->assertSame(5, $item->remaining_quantity);
    }

    public function test_color_level_delivery_acceptance_scenario(): void
    {
        [$order, $item] = $this->multiColorOrder(['Black', 'White'], requestedPerColor: 10);

        $black = $this->colorRow($item, 'Black');
        $white = $this->colorRow($item, 'White');

        // Scenario A: 5 pieces delivered from each color.
        $order->recordDeliveries([
            $black->id => ['quantity' => 5, 'expected_delivered' => 0],
            $white->id => ['quantity' => 5, 'expected_delivered' => 0],
        ]);

        $card = ProductionRequirementsWidget::requirementsFor()->first();

        $this->assertSame([
            ['color' => 'Black', 'remaining' => 5],
            ['color' => 'White', 'remaining' => 5],
        ], $card['pending_colors']);
        $this->assertSame(5, $card['uniform_remaining']);

        // Scenario B: Black completed, White still pending.
        $order->recordDeliveries([$black->id => ['quantity' => 5, 'expected_delivered' => 5]]);

        $card = ProductionRequirementsWidget::requirementsFor()->first();

        $this->assertSame([['color' => 'White', 'remaining' => 5]], $card['pending_colors']);
        $this->assertSame(5, $card['uniform_remaining']);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $this->assertSame(1, $page->instance()->pendingColorsCount());
        $page->assertSee('White')->assertDontSee('Black');

        // Scenario C: White completed; nothing is left for production.
        $order->recordDeliveries([$white->id => ['quantity' => 5, 'expected_delivered' => 5]]);

        $this->assertTrue(ProductionRequirementsWidget::requirementsFor()->isEmpty());
        $this->assertSame(0, OrderItemColorQuantity::outstandingColorCount());
        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertSame(20, $item->refresh()->delivered_quantity);
    }

    public function test_order_number_links_to_the_order_view_page(): void
    {
        [$order, $item] = $this->orderWithItem(OrderStatus::Confirmed, 5);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->assertCanSeeTableRecords([$this->colorRow($item, 'Black')])
            ->assertSee("/admin/order-management/{$order->id}", false);
    }

    public function test_search_matches_order_number_and_product_code(): void
    {
        $productA = $this->newProduct(['name' => 'Shoe A', 'product_code' => 'SH-A']);
        $productB = $this->newProduct(['name' => 'Shoe B', 'product_code' => 'SH-B']);

        [$orderA, $itemA] = $this->orderWithItem(OrderStatus::Confirmed, 5, $productA);
        [, $itemB] = $this->orderWithItem(OrderStatus::Confirmed, 4, $productB);

        $page = Livewire::actingAs(User::factory()->create())->test(ProductionRequirements::class);

        $page->searchTable('SH-A')
            ->assertCanSeeTableRecords([$this->colorRow($itemA, 'Black')])
            ->assertCanNotSeeTableRecords([$this->colorRow($itemB, 'Black')]);

        $page->searchTable($orderA->order_number)
            ->assertCanSeeTableRecords([$this->colorRow($itemA, 'Black')])
            ->assertCanNotSeeTableRecords([$this->colorRow($itemB, 'Black')]);
    }

    public function test_empty_state_when_nothing_is_outstanding(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirements::class)
            ->assertSee('لا توجد كميات مطلوبة للتشغيل حالياً');

        $this->assertSame(0, OrderItemColorQuantity::outstandingColorCount());
    }

    public function test_page_renders_with_the_updated_terminology_and_western_digits(): void
    {
        [, $item] = $this->orderWithItem(OrderStatus::Confirmed, 1234);

        $this->actingAs(User::factory()->create())
            ->get('/admin/production-requirements')
            ->assertOk()
            ->assertSee('المطلوب للتشغيل')
            ->assertSee('1,234')
            ->assertDontSee('١٬٢٣٤');

        $this->assertSame(1234, $this->colorRow($item, 'Black')->requested_quantity);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/production-requirements')->assertRedirect('/admin/login');
    }

    public function test_admin_can_open_the_page_with_and_without_product_filter(): void
    {
        $product = $this->newProduct();

        $this->orderWithItem(OrderStatus::Confirmed, 5, $product);

        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/production-requirements')->assertOk();
        $this->actingAs($admin)->get("/admin/production-requirements?product={$product->id}")->assertOk();
    }
}
