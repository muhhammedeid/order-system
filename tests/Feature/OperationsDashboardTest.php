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
            $order->recordDeliveries([$item->id => ['quantity' => $delivered, 'expected_delivered' => 0]]);
        }

        return [$order->refresh(), $item->refresh()];
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

        $confirmedOrder->recordDeliveries([$confirmedItem->id => ['quantity' => 25, 'expected_delivered' => 0]]);

        $this->assertSame(1, Order::confirmedInExecutionCount());
        $this->assertSame(1, $this->statsByLabel()['طلبات مؤكدة']->getValue());

        $partialOrder->recordDeliveries([$partialItem->id => ['quantity' => 15, 'expected_delivered' => 10]]);

        $this->assertSame(0, Order::confirmedInExecutionCount());
        $this->assertSame(0, $this->statsByLabel()['طلبات مؤكدة']->getValue());
    }

    public function test_outstanding_kpi_includes_only_confirmed_and_partially_delivered_remaining_units(): void
    {
        $this->orderWithItem(OrderStatus::New, 10);
        $this->orderWithItem(OrderStatus::Cancelled, 10);
        $this->orderWithItem(OrderStatus::Delivered, 10, 10);
        $this->orderWithItem(OrderStatus::Confirmed, 5);
        [$partialOrder, $partialItem] = $this->orderWithItem(OrderStatus::PartiallyDelivered, 8, 3);

        $stats = $this->statsByLabel();

        $this->assertSame(10, $stats['المطلوب للتشغيل']->getValue());
        $this->assertSame(5, $partialItem->remaining_quantity);

        $partialOrder->recordDeliveries([$partialItem->id => ['quantity' => 2, 'expected_delivered' => 3]]);

        $this->assertSame(8, $this->statsByLabel()['المطلوب للتشغيل']->getValue());
    }

    public function test_outstanding_kpi_excludes_fully_delivered_items_and_zero_outstanding_products(): void
    {
        $product = $this->newProduct();

        [$order, $item] = $this->orderWithItem(OrderStatus::Confirmed, 4, 0, $product);

        $order->recordDeliveries([$item->id => ['quantity' => 4, 'expected_delivered' => 0]]);

        $this->assertSame(0, OrderItem::outstandingQuantityTotal());
        $this->assertSame(0, $this->statsByLabel()['المطلوب للتشغيل']->getValue());
        $this->assertTrue(OrderItem::productProductionTotals()->isEmpty());
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
        $this->assertStringContainsString('/admin/production-requirements', $stats['المطلوب للتشغيل']->getUrl());
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

        $this->assertSame(8, $rowA['required_quantity']);
        $this->assertSame(2, $rowA['orders_count']);
        $this->assertSame(url('/storage/products/images/a.jpg'), $rowA['image']);
        $this->assertSame('SH-A', $rowA['code']);

        $rowB = $requirements->firstWhere('id', $productB->id);

        $this->assertSame(3, $rowB['required_quantity']);
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
            ->assertSee('المطلوب للتشغيل')
            ->assertSee('منتجات نشطة')
            ->assertSee('العملاء');
    }

    public function test_production_requirements_widget_renders_cards(): void
    {
        $this->orderWithItem(OrderStatus::Confirmed, 5);

        Livewire::actingAs(User::factory()->create())
            ->test(ProductionRequirementsWidget::class)
            ->assertSee('المطلوب للتشغيل حالياً')
            ->assertSee('الكمية المطلوبة: 5')
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
