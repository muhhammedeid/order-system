<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\ProductionRequirements;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\ProductionRequirementsWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemColorQuantity;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
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

    private function newOrder(OrderStatus $status = OrderStatus::New): Order
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
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
    private function orderWithItem(OrderStatus $status, int $quantity, int $delivered = 0, ?Product $product = null, ?ProductVariant $variant = null): array
    {
        $product ??= $this->newProduct();
        $variant ??= $this->newVariant($product);

        $order = $this->newOrder($status);

        $item = $order->items()->create(
            OrderItem::snapshotFromVariant($variant) + ['quantity' => $quantity]
        );

        $order->recalculateTotalQuantity();

        if ($delivered > 0) {
            $item->colorQuantities()->firstOrFail()->setDeliveredQuantity($delivered);
            $order->refreshDeliveryStatus();
        }

        return [$order->refresh(), $item->refresh()];
    }

    /**
     * Confirmed order with one line covering several snapshot colors.
     *
     * @param  array<int, string>  $colors
     * @return array{0: Order, 1: OrderItem}
     */
    private function multiColorOrder(array $colors = ['Black', 'White'], int $requestedPerColor = 10, OrderStatus $status = OrderStatus::Confirmed): array
    {
        $product = $this->newProduct([
            'color_enabled' => false,
            'size_enabled' => false,
        ]);

        $variants = collect($colors)->map(fn (string $color): ProductVariant => $this->newVariant($product, $color, '37'));

        $order = $this->newOrder($status);

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

    /**
     * @return array<string, mixed>
     */
    private function statsByLabel(): array
    {
        $widget = new OrderStatsWidget;

        $stats = (fn (): array => $this->getStats())->call($widget);

        return collect($stats)->mapWithKeys(fn ($stat) => [$stat->getLabel() => $stat])->all();
    }

    public function test_status_kpis_match_their_source_counts(): void
    {
        $this->orderWithItem(OrderStatus::New, 5);
        $this->orderWithItem(OrderStatus::Confirmed, 5);
        $this->orderWithItem(OrderStatus::PartiallyDelivered, 5, 2);
        $this->orderWithItem(OrderStatus::Delivered, 5, 5);
        $this->orderWithItem(OrderStatus::Cancelled, 5);

        $stats = $this->statsByLabel();

        $this->assertSame(1, $stats['طلبات جديدة']->getValue());
        $this->assertSame(2, $stats['طلبات مؤكدة']->getValue());
        $this->assertSame(1, $stats['تسليم جزئي']->getValue());
        $this->assertSame(1, $stats['طلبات مُسلَّمة']->getValue());

        $this->assertSame(Order::query()->where('status', OrderStatus::New->value)->count(), $stats['طلبات جديدة']->getValue());
        $this->assertSame(Order::confirmedInExecutionCount(), $stats['طلبات مؤكدة']->getValue());
        $this->assertSame(Order::query()->where('status', OrderStatus::PartiallyDelivered->value)->count(), $stats['تسليم جزئي']->getValue());
        $this->assertSame(Order::query()->where('status', OrderStatus::Delivered->value)->count(), $stats['طلبات مُسلَّمة']->getValue());
    }

    public function test_confirmed_kpi_counts_orders_in_execution_until_fully_delivered(): void
    {
        $this->orderWithItem(OrderStatus::New, 5);
        [$confirmedOrder, $confirmedItem] = $this->orderWithItem(OrderStatus::Confirmed, 25);
        [$partialOrder, $partialItem] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 25, 10);
        $this->orderWithItem(OrderStatus::Delivered, 5, 5);
        $this->orderWithItem(OrderStatus::Cancelled, 5);

        $this->assertSame(2, Order::confirmedInExecutionCount());
        $this->assertSame(2, $this->statsByLabel()['طلبات مؤكدة']->getValue());

        $this->colorRow($confirmedItem, 'Black')->setDeliveredQuantity(25);
        $confirmedOrder->refreshDeliveryStatus();

        $this->assertSame(1, Order::confirmedInExecutionCount());
        $this->assertSame(1, $this->statsByLabel()['طلبات مؤكدة']->getValue());

        $this->colorRow($partialItem, 'Black')->setDeliveredQuantity(25);
        $partialOrder->refreshDeliveryStatus();

        $this->assertSame(0, Order::confirmedInExecutionCount());
        $this->assertSame(0, $this->statsByLabel()['طلبات مؤكدة']->getValue());
    }

    public function test_outstanding_kpi_sums_pieces_still_requiring_production(): void
    {
        $this->orderWithItem(OrderStatus::New, 10);
        $this->orderWithItem(OrderStatus::Cancelled, 10);
        $this->orderWithItem(OrderStatus::Delivered, 10, 10);
        $this->orderWithItem(OrderStatus::Confirmed, 5);
        [$partialOrder, $partialItem] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8, 3);

        $stats = $this->statsByLabel();

        $this->assertSame('10 قطعة', $stats['إجمالي الطلبات']->getValue());
        $this->assertSame(5, $partialItem->remaining_quantity);
        $this->assertSame(5, $this->colorRow($partialItem, 'Black')->remaining_quantity);

        $partialOrder->recordDeliveries([
            $this->colorRow($partialItem, 'Black')->id => ['quantity' => 2, 'expected_delivered' => 3],
        ]);

        $this->assertSame('8 قطعة', $this->statsByLabel()['إجمالي الطلبات']->getValue());

        $partialOrder->recordDeliveries([
            $this->colorRow($partialItem, 'Black')->id => ['quantity' => 3, 'expected_delivered' => 5],
        ]);

        $this->assertSame('5 قطعة', $this->statsByLabel()['إجمالي الطلبات']->getValue());
    }

    public function test_outstanding_kpi_excludes_fully_delivered_colors_and_zero_outstanding_products(): void
    {
        $product = $this->newProduct();

        [$order, $item] = $this->orderWithItem(OrderStatus::Confirmed, 4, 0, $product);

        $order->recordDeliveries([
            $this->colorRow($item, 'Black')->id => ['quantity' => 4, 'expected_delivered' => 0],
        ]);

        $this->assertSame(0, OrderItemColorQuantity::outstandingColorCount());
        $this->assertSame('0 قطعة', $this->statsByLabel()['إجمالي الطلبات']->getValue());
        $this->assertTrue(OrderItemColorQuantity::productionCards()->isEmpty());
    }

    public function test_total_orders_card_uses_existing_piece_distribution_across_colors(): void
    {
        [$order, $item] = $this->multiColorOrder(['Black', 'White', 'Beige'], 10);

        $this->assertSame('30 قطعة', $this->statsByLabel()['إجمالي الطلبات']->getValue());

        $order->recordDeliveries([
            $this->colorRow($item, 'White')->id => ['quantity' => 4, 'expected_delivered' => 0],
        ]);

        $this->assertSame('26 قطعة', $this->statsByLabel()['إجمالي الطلبات']->getValue());
        $this->assertSame(30, $item->refresh()->quantity);
        $this->assertSame(10, $item->requested_quantity);
        $this->assertSame(3, $item->color_count);
    }

    public function test_active_products_and_customers_cards(): void
    {
        $this->newProduct(['active' => true]);
        $this->newProduct(['active' => true]);
        $this->newProduct(['active' => false]);
        Customer::factory()->count(3)->create();

        $stats = $this->statsByLabel();

        $this->assertSame(2, $stats['منتجات نشطة']->getValue());
        $this->assertSame(3, $stats['العملاء']->getValue());
    }

    public function test_todays_orders_uses_cairo_business_day(): void
    {
        $order = $this->newOrder();

        Carbon::setTestNow(Carbon::parse('2026-09-19 21:30:00', 'UTC'));

        $order->forceFill(['created_at' => Carbon::parse('2026-09-19 21:30:00', 'UTC')])->save();

        $this->assertSame(1, $this->statsByLabel()['طلبات اليوم']->getValue(), 'Order created after Cairo midnight must count for the new business day.');

        $order->forceFill(['created_at' => Carbon::parse('2026-09-19 20:30:00', 'UTC')])->save();

        $this->assertSame(0, $this->statsByLabel()['طلبات اليوم']->getValue(), 'Order created before Cairo midnight belongs to the previous business day.');

        $order->forceFill(['created_at' => Carbon::parse('2026-09-19 21:05:00', 'UTC')])->save();

        $this->assertSame(1, $this->statsByLabel()['طلبات اليوم']->getValue());

        Carbon::setTestNow();
    }

    public function test_kpi_links_match_their_destination_filters(): void
    {
        $stats = $this->statsByLabel();

        $this->assertStringContainsString('/admin/order-management?tab=new', $stats['طلبات جديدة']->getUrl());
        $this->assertStringContainsString('/admin/order-management?tab=confirmed', $stats['طلبات مؤكدة']->getUrl());
        $this->assertStringContainsString('/admin/order-management?tab=partially_delivered', $stats['تسليم جزئي']->getUrl());
        $this->assertStringContainsString('/admin/orders', $stats['طلبات مُسلَّمة']->getUrl());
        $this->assertStringContainsString('/admin/production-requirements', $stats['إجمالي الطلبات']->getUrl());
        $this->assertStringContainsString('/admin/products?filters', $stats['منتجات نشطة']->getUrl());
        $this->assertStringContainsString('/admin/customers', $stats['العملاء']->getUrl());
    }

    public function test_production_requirements_widget_groups_by_product_and_links_to_drill_down(): void
    {
        $productA = $this->newProduct(['name' => 'Shoe A', 'product_code' => 'SH-A']);
        $productB = $this->newProduct(['name' => 'Shoe B', 'product_code' => 'SH-B']);

        ProductImage::create([
            'product_id' => $productA->id,
            'image_path' => 'products/images/a.jpg',
            'sort_order' => 0,
        ]);

        $this->orderWithItem(OrderStatus::Confirmed, 5, 0, $productA, $this->newVariant($productA, 'Black', '41'));
        $this->orderWithItem(OrderStatus::Confirmed, 3, 0, $productA, $this->newVariant($productA, 'White', '42'));
        $this->orderWithItem(OrderStatus::PartiallyDelivered, 4, 1, $productB, $this->newVariant($productB, 'Red', '40'));

        $widget = new ProductionRequirementsWidget;
        $requirements = (fn () => $this->getViewData()['requirements'])->call($widget);

        $this->assertCount(2, $requirements);

        $rowA = $requirements->firstWhere('id', $productA->id);

        $this->assertSame([
            ['color' => 'Black', 'remaining' => 5],
            ['color' => 'White', 'remaining' => 3],
        ], $rowA['pending_colors']);
        $this->assertNull($rowA['uniform_remaining']);
        $this->assertSame(2, $rowA['orders_count']);
        $this->assertSame(url('/storage/products/images/a.jpg'), $rowA['image']);
        $this->assertSame('SH-A', $rowA['code']);

        $rowB = $requirements->firstWhere('id', $productB->id);

        $this->assertSame([['color' => 'Red', 'remaining' => 3]], $rowB['pending_colors']);
        $this->assertSame(3, $rowB['uniform_remaining']);
        $this->assertNull($rowB['image']);
    }

    public function test_production_requirements_widget_shows_inactive_products_with_outstanding_quantity(): void
    {
        $product = $this->newProduct(['active' => false]);

        $this->orderWithItem(OrderStatus::Confirmed, 6, 0, $product);

        $widget = new ProductionRequirementsWidget;
        $requirements = (fn () => $this->getViewData()['requirements'])->call($widget);

        $this->assertCount(1, $requirements);
        $this->assertFalse($requirements->first()['active']);
    }

    public function test_card_shows_the_outstanding_quantity_of_every_pending_color(): void
    {
        [, $item] = $this->multiColorOrder(['Black', 'White']);

        $this->colorRow($item, 'Black')->setDeliveredQuantity(5);
        $this->colorRow($item, 'White')->setDeliveredQuantity(5);

        $requirement = ProductionRequirementsWidget::requirementsFor()->first();

        $this->assertSame([
            ['color' => 'Black', 'remaining' => 5],
            ['color' => 'White', 'remaining' => 5],
        ], $requirement['pending_colors']);
        $this->assertSame(5, $requirement['uniform_remaining']);
    }

    public function test_fully_delivered_colors_disappear_from_the_card(): void
    {
        [, $item] = $this->multiColorOrder(['Black', 'White', 'Beige']);

        $this->colorRow($item, 'Black')->setDeliveredQuantity(10);
        $this->colorRow($item, 'White')->setDeliveredQuantity(5);

        $requirement = ProductionRequirementsWidget::requirementsFor()->first();

        $this->assertSame([
            ['color' => 'White', 'remaining' => 5],
            ['color' => 'Beige', 'remaining' => 10],
        ], $requirement['pending_colors']);
        $this->assertNull($requirement['uniform_remaining']);
    }

    public function test_card_never_shows_the_original_quantity_after_a_partial_delivery(): void
    {
        [, $item] = $this->multiColorOrder(['Black', 'White'], requestedPerColor: 10);

        $this->colorRow($item, 'Black')->setDeliveredQuantity(5);
        $this->colorRow($item, 'White')->setDeliveredQuantity(5);

        $rendered = Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirementsWidget::class)
            ->assertSee('المتبقي للتشغيل حسب اللون')
            ->assertSee('الألوان المتبقية')
            ->assertSee('5 قطعة متبقية')
            ->assertDontSee('10 قطعة متبقية');

        $requirement = ProductionRequirementsWidget::requirementsFor()->first();

        $this->assertSame(5, $requirement['uniform_remaining']);
        $this->assertSame($rendered->html() !== '', true);
    }

    public function test_card_shows_mixed_quantities_label_when_remainders_differ(): void
    {
        [, $item] = $this->multiColorOrder(['Black', 'White', 'Beige']);

        $this->colorRow($item, 'Black')->setDeliveredQuantity(10);
        $this->colorRow($item, 'White')->setDeliveredQuantity(5);

        $requirement = ProductionRequirementsWidget::requirementsFor()->first();

        $this->assertNull($requirement['uniform_remaining']);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirementsWidget::class)
            ->assertSee('كميات مختلفة حسب اللون')
            ->assertSee('5 قطعة متبقية')
            ->assertSee('10 قطعة متبقية');
    }

    public function test_products_without_pending_colors_disappear_from_the_cards(): void
    {
        [, $item] = $this->multiColorOrder(['Black', 'White']);

        $this->colorRow($item, 'Black')->setDeliveredQuantity(10);
        $this->colorRow($item, 'White')->setDeliveredQuantity(10);

        $this->assertTrue(ProductionRequirementsWidget::requirementsFor()->isEmpty());
        $this->assertSame(0, OrderItemColorQuantity::outstandingColorCount());

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirementsWidget::class)
            ->assertSee('لا توجد كميات مطلوبة للتشغيل حالياً');
    }

    public function test_cards_use_snapshot_colors_after_product_colors_change(): void
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

        // The product later gains and loses colors; the order must not change.
        $this->newVariant($product, 'Beige', '37');
        $product->variants()->where('color', 'Black')->delete();

        $requirement = ProductionRequirementsWidget::requirementsFor()->first();

        $this->assertSame([
            ['color' => 'Black', 'remaining' => 10],
            ['color' => 'White', 'remaining' => 10],
        ], $requirement['pending_colors']);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirementsWidget::class)
            ->assertSee('Black')
            ->assertSee('White')
            ->assertDontSee('Beige');
    }

    public function test_active_products_kpi_filter_matches_the_products_source_view(): void
    {
        $active = $this->newProduct(['active' => true]);
        $inactive = $this->newProduct(['active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(ListProducts::class)
            ->filterTable('active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_dashboard_page_renders_with_widgets_and_rtl(): void
    {
        $this->orderWithItem(OrderStatus::Confirmed, 5);

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSeeLivewire(OrderStatsWidget::class)
            ->assertSeeLivewire(ProductionRequirementsWidget::class);
    }

    public function test_stats_widget_renders_kpi_labels_and_links(): void
    {
        $this->orderWithItem(OrderStatus::Confirmed, 5);

        Livewire::actingAs(User::factory()->create())
            ->test(OrderStatsWidget::class)
            ->assertSee('طلبات جديدة')
            ->assertSee('إجمالي الطلبات')
            ->assertSee('منتجات نشطة')
            ->assertSee('العملاء');
    }

    public function test_production_requirements_widget_renders_cards(): void
    {
        $this->orderWithItem(OrderStatus::Confirmed, 5);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirementsWidget::class)
            ->assertSee('المطلوب للتشغيل حالياً')
            ->assertSee('المتبقي للتشغيل حسب اللون')
            ->assertSee('5')
            ->assertSee('5 قطعة متبقية')
            ->assertSee('عرض الطلبات المساهمة');
    }

    public function test_production_requirements_widget_renders_empty_state_without_outstanding_quantities(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirementsWidget::class)
            ->assertSee('لا توجد كميات مطلوبة للتشغيل حالياً');
    }

    public function test_production_requirements_page_is_guarded_by_admin_authentication(): void
    {
        $this->get('/admin/production-requirements')->assertRedirect('/admin/login');
    }

    public function test_navigation_includes_production_requirements_page(): void
    {
        $this->assertSame('المطلوب للتشغيل', ProductionRequirements::getNavigationLabel());

        $this->actingAs(User::factory()->create())
            ->get('/admin/production-requirements')
            ->assertOk();
    }
}
