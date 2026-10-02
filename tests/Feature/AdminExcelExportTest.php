<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\ProductionRequirements;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\OrderManagement\Pages\ListOrders as ListManagedOrders;
use App\Filament\Resources\OrderManagement\Pages\ViewOrder as ViewManagedOrder;
use App\Filament\Resources\Orders\Pages\ListOrders as ListDeliveredOrders;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\VariantColors\Pages\ListVariantColors;
use App\Filament\Resources\VariantSizes\Pages\ListVariantSizes;
use App\Filament\Widgets\ProductionRequirementsWidget;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemColorQuantity;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantColor;
use App\Models\VariantSize;
use App\Support\Exports\BusinessExport;
use App\Support\Exports\CustomersExport;
use App\Support\Exports\OrderItemsExport;
use App\Support\Exports\ProductsExport;
use App\Support\Exports\ProductVariantsExport;
use App\Support\Imports\CustomersImporter;
use App\Support\Imports\ProductsImporter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class AdminExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-09-19 12:00:00';

    /**
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Carbon::setTestNow(Carbon::parse(self::NOW, 'UTC'));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function makeProduct(array $attributes = []): Product
    {
        return Product::factory()->create([
            'size_enabled' => true,
            'price_visibility' => 'public',
            'price' => 100,
            'active' => true,
            ...$attributes,
        ]);
    }

    private function makeVariant(Product $product, string $color = 'Black', ?string $size = '41'): ProductVariant
    {
        return ProductVariant::factory()->for($product)->create([
            'color' => $color,
            'size' => $size,
            'available_quantity' => 0,
        ]);
    }

    /**
     * @param  array<int, array{variant: ProductVariant, quantity: int, deliver?: int}>  $lines
     */
    private function makeOrder(OrderStatus $status, array $lines, ?Customer $customer = null): Order
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => ($customer ?? Customer::factory()->create())->id,
            'total_quantity' => 0,
        ]);

        $deliveries = [];

        foreach ($lines as $line) {
            $item = $order->items()->create(
                OrderItem::snapshotFromVariant($line['variant']) + ['quantity' => $line['quantity']]
            );

            if (($line['deliver'] ?? 0) > 0) {
                $colorRow = $item->colorQuantities()->firstOrFail();
                $deliveries[$colorRow->id] = ['quantity' => $line['deliver'], 'expected_delivered' => 0];
            }
        }

        $order->recalculateTotalQuantity();

        if ($status === OrderStatus::Cancelled) {
            $order->cancel();
        } elseif ($status !== OrderStatus::New) {
            $order->confirm();

            if ($status === OrderStatus::Delivered) {
                $order->deliverAllRemaining();
            } elseif ($deliveries !== []) {
                $order->recordDeliveries($deliveries);
            }
        }

        return $order->refresh();
    }

    private function timestamped(string $prefix): string
    {
        return $prefix.'-'.now('Africa/Cairo')->format('Y-m-d-His').'.xlsx';
    }

    private function writeTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'r04');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function sheetFromDownload(Testable $component, string $expectedFileName): Worksheet
    {
        $download = $component->effects['download'] ?? null;

        $this->assertIsArray($download, 'Expected a file download effect.');
        $this->assertSame($expectedFileName, $download['name']);

        return IOFactory::load($this->writeTempFile(base64_decode($download['content'])))->getActiveSheet();
    }

    private function sheetFromExport(BusinessExport $export): Worksheet
    {
        return IOFactory::load($this->writeTempFile(Excel::raw($export, ExcelWriter::XLSX)))->getActiveSheet();
    }

    /**
     * @return array<int, string>
     */
    private function headers(Worksheet $sheet): array
    {
        return array_map(
            fn ($value) => (string) $value,
            $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0],
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function column(Worksheet $sheet, string $column): array
    {
        if ($sheet->getHighestRow() < 2) {
            return [];
        }

        return array_map(
            fn (array $row) => $row[0],
            $sheet->rangeToArray($column.'2:'.$column.$sheet->getHighestRow()),
        );
    }

    public function test_export_headers_match_the_approved_contracts(): void
    {
        $this->assertSame(ProductsImporter::HEADERS, (new ProductsExport(Product::query()))->headings());
        $this->assertSame(CustomersImporter::HEADERS, (new CustomersExport(Customer::query()))->headings());
    }

    public function test_products_export_respects_active_filter_and_preserves_identifiers(): void
    {
        $category = Category::factory()->create(['name' => 'Men Shoes']);

        $active = $this->makeProduct([
            'product_code' => '00123',
            'name' => 'Runner',
            'category_id' => $category->id,
            'price' => 450,
        ]);

        $inactive = $this->makeProduct([
            'product_code' => 'INA-1',
            'name' => 'Retired',
            'active' => false,
        ]);

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ListProducts::class)
                ->filterTable('active', true)
                ->callAction('exportExcel'),
            $this->timestamped('products'),
        );

        $this->assertSame(['Product Code', 'Product Name', 'Category', 'Price', 'Price Visibility', 'Active'], $this->headers($sheet));
        $this->assertSame(2, $sheet->getHighestRow());

        $this->assertSame('00123', $sheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame('Runner', $sheet->getCell('B2')->getValue());
        $this->assertSame('Men Shoes', $sheet->getCell('C2')->getValue());
        $this->assertSame(450.0, (float) $sheet->getCell('D2')->getValue());
        $this->assertSame('public', $sheet->getCell('E2')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('F2')->getValue());

        $this->assertNotContains('INA-1', $this->column($sheet, 'A'));
        $this->assertNotContains('Retired', $this->column($sheet, 'B'));
    }

    public function test_empty_export_produces_a_headers_only_file(): void
    {
        $sheet = $this->sheetFromExport(new ProductsExport(Product::query()));

        $this->assertSame(1, $sheet->getHighestRow());
        $this->assertSame(ProductsImporter::HEADERS, $this->headers($sheet));
    }

    public function test_guests_cannot_reach_export_screens(): void
    {
        $this->get('/admin/products')->assertRedirect('/admin/login');
        $this->get('/admin/order-management')->assertRedirect('/admin/login');
        $this->get('/admin/production-requirements')->assertRedirect('/admin/login');
    }

    public function test_products_export_blanks_price_for_request_price_products(): void
    {
        Product::factory()->requestPrice()->create(['product_code' => 'SH-R', 'name' => 'Hidden']);

        $sheet = $this->sheetFromExport(new ProductsExport(Product::query()));

        $this->assertSame('SH-R', $sheet->getCell('A2')->getValue());
        $this->assertNull($sheet->getCell('D2')->getValue());
        $this->assertSame('request_price', $sheet->getCell('E2')->getValue());
    }

    public function test_customers_export_uses_import_headers_and_respects_search(): void
    {
        $matching = Customer::factory()->create([
            'customer_code' => 'C-001',
            'name' => 'Match',
            'phone' => '01000000001',
        ]);

        Customer::factory()->create(['name' => 'Other', 'phone' => '02000000002']);

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ListCustomers::class)
                ->searchTable('0100')
                ->callAction('exportExcel'),
            $this->timestamped('customers'),
        );

        $this->assertSame(CustomersImporter::HEADERS, $this->headers($sheet));
        $this->assertSame(2, $sheet->getHighestRow());
        $this->assertSame('C-001', $sheet->getCell('A2')->getValue());
        $this->assertSame('Match', $sheet->getCell('B2')->getValue());
        $this->assertSame('01000000001', $sheet->getCell('D2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D2')->getDataType());
    }

    public function test_order_export_rows_follow_variant_grain_with_snapshots_and_delivery_math(): void
    {
        $customer = Customer::factory()->create([
            'customer_code' => 'C-777',
            'name' => 'أحمد',
            'phone' => '01000000001',
        ]);

        $publicProduct = $this->makeProduct([
            'product_code' => 'SH-1',
            'name' => 'Runner',
            'price' => 250,
        ]);

        $requestProduct = Product::factory()->requestPrice()->create([
            'product_code' => 'SH-2',
            'name' => 'Sandal',
            'size_enabled' => false,
        ]);

        $order = $this->makeOrder(OrderStatus::PartiallyDelivered, [
            ['variant' => $this->makeVariant($publicProduct, 'Black', '41'), 'quantity' => 5, 'deliver' => 2],
            ['variant' => $this->makeVariant($requestProduct, 'Blue', null), 'quantity' => 4],
        ], $customer);

        $publicProduct->update(['name' => 'Renamed After Order', 'price' => 999]);
        $requestProduct->update(['name' => 'Renamed Too']);

        $sheet = $this->sheetFromExport(OrderItemsExport::forOrder($order->refresh()));

        $this->assertSame([
            'Order No', 'Order Date', 'Customer Code', 'Customer Name', 'Phone',
            'Product Code', 'Product Name', 'Color', 'Size',
            'Qty Per Color', 'Color Count', 'Total Pieces', 'Delivered Qty',
            'Remaining Qty', 'Status', 'Unit Price',
        ], $this->headers($sheet));

        $this->assertSame(3, $sheet->getHighestRow());

        $this->assertSame($order->order_number, $sheet->getCell('A2')->getValue());
        $this->assertSame(
            $order->created_at->timezone('Africa/Cairo')->format('Y-m-d H:i'),
            $sheet->getCell('B2')->getValue(),
        );
        $this->assertSame('C-777', $sheet->getCell('C2')->getValue());
        $this->assertSame('أحمد', $sheet->getCell('D2')->getValue());
        $this->assertSame('01000000001', $sheet->getCell('E2')->getValue());
        $this->assertSame('SH-1', $sheet->getCell('F2')->getValue());
        $this->assertSame('Runner', $sheet->getCell('G2')->getValue());
        $this->assertSame('Black', $sheet->getCell('H2')->getValue());
        $this->assertSame('41', $sheet->getCell('I2')->getValue());
        $this->assertSame(5, (int) $sheet->getCell('J2')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('K2')->getValue());
        $this->assertSame(5, (int) $sheet->getCell('L2')->getValue());
        $this->assertSame(2, (int) $sheet->getCell('M2')->getValue());
        $this->assertSame(3, (int) $sheet->getCell('N2')->getValue());
        $this->assertSame(OrderStatus::PartiallyDelivered->label(), $sheet->getCell('O2')->getValue());
        $this->assertSame(250.0, (float) $sheet->getCell('P2')->getValue());

        $this->assertSame('SH-2', $sheet->getCell('F3')->getValue());
        $this->assertSame('Sandal', $sheet->getCell('G3')->getValue());
        $this->assertNull($sheet->getCell('I3')->getValue());
        $this->assertSame(4, (int) $sheet->getCell('N3')->getValue());
        $this->assertNull($sheet->getCell('P3')->getValue());
    }

    public function test_order_export_preserves_numeric_zero_and_distinguishes_hidden_price(): void
    {
        $variant = $this->makeVariant(Product::factory()->requestPrice()->create(['size_enabled' => true]));
        $newOrder = $this->makeOrder(OrderStatus::New, [['variant' => $variant, 'quantity' => 5]]);
        $deliveredOrder = $this->makeOrder(OrderStatus::Delivered, [['variant' => $variant, 'quantity' => 5]]);

        $file = $this->writeTempFile(Excel::raw(
            OrderItemsExport::forOrderIds([$newOrder->id, $deliveredOrder->id]),
            ExcelWriter::XLSX,
        ));
        $exportBinder = Cell::getValueBinder();
        Cell::setValueBinder(new DefaultValueBinder);
        try {
            $sheet = IOFactory::load($file)->getActiveSheet();
        } finally {
            Cell::setValueBinder($exportBinder);
        }

        $this->assertNotNull($sheet->getCell('M2')->getValue());
        $this->assertSame(0, (int) $sheet->getCell('M2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('M2')->getDataType());
        $this->assertNotNull($sheet->getCell('N3')->getValue());
        $this->assertSame(0, (int) $sheet->getCell('N3')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('N3')->getDataType());
        $this->assertNull($sheet->getCell('P2')->getValue());
        $this->assertNull($sheet->getCell('P3')->getValue());
    }

    public function test_order_management_export_respects_the_active_status_tab(): void
    {
        $variant = $this->makeVariant($this->makeProduct());

        $newOrder = $this->makeOrder(OrderStatus::New, [['variant' => $variant, 'quantity' => 1]]);
        $confirmedOrder = $this->makeOrder(OrderStatus::Confirmed, [['variant' => $variant, 'quantity' => 2]]);

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ListManagedOrders::class)
                ->set('activeTab', 'confirmed')
                ->callAction('exportExcel'),
            $this->timestamped('orders'),
        );

        $this->assertSame([$confirmedOrder->order_number], $this->column($sheet, 'A'));
        $this->assertNotContains($newOrder->order_number, $this->column($sheet, 'A'));
    }

    public function test_delivered_orders_export_contains_only_delivered_orders(): void
    {
        $variant = $this->makeVariant($this->makeProduct());

        $deliveredOrder = $this->makeOrder(OrderStatus::Delivered, [['variant' => $variant, 'quantity' => 3]]);
        $confirmedOrder = $this->makeOrder(OrderStatus::Confirmed, [['variant' => $variant, 'quantity' => 2]]);

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ListDeliveredOrders::class)
                ->callAction('exportExcel'),
            $this->timestamped('delivered-orders'),
        );

        $this->assertSame([$deliveredOrder->order_number], $this->column($sheet, 'A'));
        $this->assertNotContains($confirmedOrder->order_number, $this->column($sheet, 'A'));
    }

    public function test_production_requirements_export_respects_product_filter(): void
    {
        $productA = $this->makeProduct(['product_code' => 'SH-A', 'name' => 'Shoe A']);
        $productB = $this->makeProduct(['product_code' => 'SH-B', 'name' => 'Shoe B']);

        $this->makeOrder(OrderStatus::Confirmed, [
            ['variant' => $this->makeVariant($productA, 'Black', '41'), 'quantity' => 5],
            ['variant' => $this->makeVariant($productB, 'White', '42'), 'quantity' => 4],
        ]);

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ProductionRequirements::class, ['product' => $productA->id])
                ->callAction('exportExcel'),
            $this->timestamped('production-requirements'),
        );

        $this->assertSame(2, $sheet->getHighestRow());
        $this->assertSame('SH-A', $sheet->getCell('F2')->getValue());
        $this->assertNotContains('SH-B', $this->column($sheet, 'F'));
    }

    public function test_production_requirements_export_lists_one_row_per_pending_color(): void
    {
        $product = $this->makeProduct([
            'product_code' => 'SH-M',
            'name' => 'Multi Color',
            'color_enabled' => false,
            'size_enabled' => false,
        ]);

        $black = $this->makeVariant($product, 'Black', '37');
        $this->makeVariant($product, 'White', '37');
        $this->makeVariant($product, 'Beige', '37');

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        $snapshot = OrderItem::snapshotFromVariant($black);
        $snapshot['color'] = 'Black، White، Beige';

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => 5,
            'color_count' => 3,
            'quantity' => 15,
        ]);

        $order->recalculateTotalQuantity();
        $order->confirm();

        $blackRow = $item->colorQuantities()->where('color', 'Black')->firstOrFail();
        $whiteRow = $item->colorQuantities()->where('color', 'White')->firstOrFail();

        // Black is fully delivered, White is partially delivered.
        $blackRow->setDeliveredQuantity(5);
        $whiteRow->setDeliveredQuantity(2);
        $order->refreshDeliveryStatus();

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ProductionRequirements::class)
                ->callAction('exportExcel'),
            $this->timestamped('production-requirements'),
        );

        // One row per pending color: White and Beige only.
        $this->assertSame(3, $sheet->getHighestRow());

        $this->assertSame([
            'Order No', 'Order Date', 'Customer Code', 'Customer Name', 'Phone',
            'Product Code', 'Product Name', 'Color', 'Size',
            'Requested Qty', 'Delivered Qty', 'Remaining Qty', 'Status',
        ], $this->headers($sheet));

        $this->assertSame('White', $sheet->getCell('H2')->getValue());
        $this->assertSame(5, (int) $sheet->getCell('J2')->getValue());
        $this->assertSame(2, (int) $sheet->getCell('K2')->getValue());
        $this->assertSame(3, (int) $sheet->getCell('L2')->getValue());

        $this->assertSame('Beige', $sheet->getCell('H3')->getValue());
        $this->assertSame(5, (int) $sheet->getCell('J3')->getValue());
        $this->assertSame(0, (int) $sheet->getCell('K3')->getValue());
        $this->assertSame(5, (int) $sheet->getCell('L3')->getValue());

        $this->assertNotContains('Black', $this->column($sheet, 'H'));
        $this->assertSame('SH-M', $sheet->getCell('F2')->getValue());
        $this->assertSame('37', $sheet->getCell('I2')->getValue());

        // The stored aggregate still reflects the physical delivered total.
        $this->assertSame(7, $item->refresh()->delivered_quantity);
    }

    public function test_production_requirements_surfaces_agree_on_color_level_remainders(): void
    {
        $product = $this->makeProduct([
            'product_code' => 'SH-X',
            'name' => 'Consistent',
            'color_enabled' => false,
            'size_enabled' => false,
        ]);

        $black = $this->makeVariant($product, 'Black', '37');
        $this->makeVariant($product, 'White', '37');
        $this->makeVariant($product, 'Beige', '37');

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        $snapshot = OrderItem::snapshotFromVariant($black);
        $snapshot['color'] = 'Black، White، Beige';

        $item = $order->items()->create($snapshot + [
            'requested_quantity' => 10,
            'color_count' => 3,
            'quantity' => 30,
        ]);

        $order->recalculateTotalQuantity();
        $order->confirm();

        $blackRow = $item->colorQuantities()->where('color', 'Black')->firstOrFail();
        $whiteRow = $item->colorQuantities()->where('color', 'White')->firstOrFail();
        $beigeRow = $item->colorQuantities()->where('color', 'Beige')->firstOrFail();

        $blackRow->setDeliveredQuantity(10);
        $whiteRow->setDeliveredQuantity(4);
        $order->refreshDeliveryStatus();

        $expected = [
            'White' => 6,
            'Beige' => 10,
        ];

        // Cards.
        $card = ProductionRequirementsWidget::requirementsFor()->first();
        $cardRemainders = collect($card['pending_colors'])->pluck('remaining', 'color')->all();

        // Drill-down table.
        $page = Livewire::actingAs($this->admin())->test(ProductionRequirements::class);
        $tableRemainders = OrderItemColorQuantity::outstandingForProduction()
            ->get()
            ->pluck('remaining_quantity', 'color')
            ->all();

        // Excel export.
        $sheet = $this->sheetFromDownload($page->callAction('exportExcel'), $this->timestamped('production-requirements'));
        $exportRemainders = [];

        foreach (range(2, $sheet->getHighestRow()) as $row) {
            $exportRemainders[(string) $sheet->getCell("H{$row}")->getValue()] = (int) $sheet->getCell("L{$row}")->getValue();
        }

        $this->assertSame($expected, $cardRemainders);
        $this->assertSame($expected, $tableRemainders);
        $this->assertSame($expected, $exportRemainders);

        // Order details: the same values, grouped by color.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get("/admin/order-management/{$order->getKey()}")
            ->assertOk()
            ->assertSee('White — 6 قطعة')
            ->assertSee('Beige — 10 قطعة')
            ->assertSee('Black — تم التسليم بالكامل');

        // Partial-delivery modal: only the colors that still require delivery.
        $component = Livewire::actingAs($admin)->test(ViewManagedOrder::class, ['record' => $order->getKey()]);
        $component->mountAction('recordDelivery');

        $this->assertSame([
            $whiteRow->id => 0,
            $beigeRow->id => 0,
        ], $component->instance()->mountedActions[0]['data']['deliveries'] ?? []);
    }

    public function test_selected_orders_bulk_export_contains_only_selected_orders(): void
    {
        $variant = $this->makeVariant($this->makeProduct());

        $orderA = $this->makeOrder(OrderStatus::Confirmed, [['variant' => $variant, 'quantity' => 5]]);
        $orderB = $this->makeOrder(OrderStatus::Confirmed, [['variant' => $variant, 'quantity' => 7]]);

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ListManagedOrders::class)
                ->set('activeTab', 'confirmed')
                ->callTableBulkAction('exportSelected', [$orderA->id]),
            $this->timestamped('orders'),
        );

        $this->assertSame([$orderA->order_number], $this->column($sheet, 'A'));
        $this->assertSame([5], array_map('intval', $this->column($sheet, 'J')));
        $this->assertNotContains($orderB->order_number, $this->column($sheet, 'A'));
    }

    public function test_single_order_export_from_view_page_contains_only_that_order(): void
    {
        $variant = $this->makeVariant($this->makeProduct());

        $order = $this->makeOrder(OrderStatus::Confirmed, [['variant' => $variant, 'quantity' => 2]]);
        $otherOrder = $this->makeOrder(OrderStatus::Confirmed, [['variant' => $variant, 'quantity' => 9]]);

        $sheet = $this->sheetFromDownload(
            Livewire::actingAs($this->admin())
                ->test(ViewManagedOrder::class, ['record' => $order->id])
                ->callAction('exportItems'),
            $this->timestamped('order-'.$order->order_number),
        );

        $this->assertSame(2, $sheet->getHighestRow());
        $this->assertSame($order->order_number, $sheet->getCell('A2')->getValue());
        $this->assertNotContains($otherOrder->order_number, $this->column($sheet, 'A'));
    }

    public function test_formula_injection_and_identifiers_are_written_as_text(): void
    {
        Customer::factory()->create([
            'customer_code' => '=1+1',
            'name' => '=HYPERLINK("http://evil.example","x")',
            'company_name' => '+SUM(1,1)',
            'phone' => '+201234567890',
        ]);

        $sheet = $this->sheetFromExport(new CustomersExport(Customer::query()));

        $this->assertSame('=1+1', $sheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame('=HYPERLINK("http://evil.example","x")', $sheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame('+SUM(1,1)', $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
        $this->assertSame('+201234567890', $sheet->getCell('D2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D2')->getDataType());

        $product = $this->makeProduct(['product_code' => '00123456789012345678']);

        $productSheet = $this->sheetFromExport(new ProductsExport(Product::query()));

        $this->assertSame($product->product_code, $productSheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $productSheet->getCell('A2')->getDataType());
    }

    public function test_export_does_not_mutate_order_lifecycle_or_write_records(): void
    {
        $variant = $this->makeVariant($this->makeProduct());

        $order = $this->makeOrder(OrderStatus::PartiallyDelivered, [
            ['variant' => $variant, 'quantity' => 6, 'deliver' => 2],
        ]);

        $before = [
            'status' => $order->status,
            'updated_at' => $order->updated_at->toDateTimeString(),
            'items' => $order->items()->get()->map(fn (OrderItem $item) => [
                $item->id, $item->quantity, $item->delivered_quantity,
            ])->all(),
        ];

        $admin = $this->admin();

        $writes = 0;

        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) === 1) {
                $writes++;
            }
        });

        Livewire::actingAs($admin)
            ->test(ListManagedOrders::class)
            ->set('activeTab', 'partially_delivered')
            ->callAction('exportExcel')
            ->assertFileDownloaded($this->timestamped('orders'));

        $order->refresh();

        $this->assertSame(0, $writes, 'Excel export must be read-only.');
        $this->assertSame($before['status'], $order->status);
        $this->assertSame($before['updated_at'], $order->updated_at->toDateTimeString());
        $this->assertSame($before['items'], $order->items()->get()->map(fn (OrderItem $item) => [
            $item->id, $item->quantity, $item->delivered_quantity,
        ])->all());
        $this->assertNull(Order::query()->where('status', 'exported')->first());
    }

    public function test_categories_and_lookup_tables_export_their_records(): void
    {
        Category::factory()->create(['name' => 'Men', 'slug' => 'men', 'active' => true]);
        VariantColor::create(['name' => 'Black', 'sort_order' => 1, 'active' => true]);
        VariantSize::create(['name' => '41', 'sort_order' => 2, 'active' => false]);

        $admin = $this->admin();

        $categories = $this->sheetFromDownload(
            Livewire::actingAs($admin)->test(ListCategories::class)->callAction('exportExcel'),
            $this->timestamped('categories'),
        );
        $this->assertSame(['Name', 'Slug', 'Active', 'Created At'], $this->headers($categories));
        $this->assertSame('Men', $categories->getCell('A2')->getValue());
        $this->assertSame(1, (int) $categories->getCell('C2')->getValue());

        $colors = $this->sheetFromDownload(
            Livewire::actingAs($admin)->test(ListVariantColors::class)->callAction('exportExcel'),
            $this->timestamped('variant-colors'),
        );
        $this->assertSame(['Name', 'Sort Order', 'Active'], $this->headers($colors));
        $this->assertSame('Black', $colors->getCell('A2')->getValue());

        $sizes = $this->sheetFromDownload(
            Livewire::actingAs($admin)->test(ListVariantSizes::class)->callAction('exportExcel'),
            $this->timestamped('variant-sizes'),
        );
        $this->assertSame('41', $sizes->getCell('A2')->getValue());
        $this->assertSame(0, (int) $sizes->getCell('C2')->getValue());
    }

    public function test_product_variants_export_includes_product_context_and_blank_size(): void
    {
        $product = Product::factory()->create([
            'product_code' => 'SH-9',
            'name' => 'Nine',
            'size_enabled' => false,
        ]);

        $this->makeVariant($product, 'Blue', null);

        $sheet = $this->sheetFromExport(new ProductVariantsExport($product->variants()->getQuery()));

        $this->assertSame(['Product Code', 'Product Name', 'Color', 'Size', 'Available Quantity'], $this->headers($sheet));
        $this->assertSame('SH-9', $sheet->getCell('A2')->getValue());
        $this->assertSame('Nine', $sheet->getCell('B2')->getValue());
        $this->assertSame('Blue', $sheet->getCell('C2')->getValue());
        $this->assertNull($sheet->getCell('D2')->getValue());
    }

    public function test_variant_relation_manager_exposes_export_action(): void
    {
        $product = $this->makeProduct();

        $this->makeVariant($product, 'Red', '41');

        $page = Livewire::actingAs($this->admin())
            ->test(VariantsRelationManager::class, [
                'ownerRecord' => $product,
                'pageClass' => EditProduct::class,
            ])
            ->callTableAction('exportExcel');

        $sheet = $this->sheetFromDownload($page, $this->timestamped('product-variants'));

        $this->assertSame('Red', $sheet->getCell('C2')->getValue());
        $this->assertSame($product->product_code, $sheet->getCell('A2')->getValue());
    }
}
