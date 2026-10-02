<?php

namespace Tests\Production;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProductionDatabaseTest extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        DatabaseGuard::verify();
        if (! self::$migrated) {
            $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
            self::$migrated = true;
        } else {
            // Retain the verified schema while isolating fixtures. Avoid seven
            // redundant full DDL rebuilds on the local production-engine host.
            Schema::disableForeignKeyConstraints();
            try {
                foreach (Schema::getTableListing() as $table) {
                    if (! str_ends_with($table, 'migrations')) {
                        DB::table($table)->delete();
                    }
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    protected function tearDown(): void
    {
        while (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_fresh_schema_generated_keys_and_constraints(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS version')->version;
        $this->assertStringContainsString('MariaDB', $version);
        $this->assertStringContainsString('STRICT_TRANS_TABLES', DB::selectOne('SELECT @@sql_mode AS mode')->mode);
        $this->assertSame('utf8mb4_unicode_ci', DB::selectOne('SELECT @@collation_database AS collation')->collation);
        $engines = DB::select('SELECT DISTINCT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [DatabaseGuard::DATABASE]);
        $this->assertSame(['InnoDB'], array_column($engines, 'engine'));

        $product = Product::factory()->create();
        // Historical schema permits NULL color; current application validation
        // requires a color. Insert directly to exercise the DB invariant itself.
        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $product->id, 'color' => null, 'size' => null, 'available_quantity' => 0,
        ]);
        $keys = DB::table('product_variants')->find($variantId);
        $this->assertSame('', $keys->color_key);
        $this->assertSame('', $keys->size_key);
        $this->rejects(fn () => DB::table('product_variants')->insert([
            'product_id' => $product->id, 'color' => null, 'size' => null, 'available_quantity' => 0,
        ]), 1062);
        $this->rejects(fn () => DB::table('product_variants')->insert([
            'product_id' => $product->id, 'color' => '', 'size' => '', 'available_quantity' => 0,
        ]), 1062);
        $this->rejects(fn () => DB::table('product_variants')->where('id', $variantId)->update(['available_quantity' => -1]), 1264);
        [$order, $item] = $this->orderFixture();
        $row = $item->colorQuantities()->firstOrFail();
        $this->rejects(fn () => DB::table('order_items')->where('id', $item->id)->update(['delivered_quantity' => 6]), 4025);
        $this->rejects(fn () => DB::table('order_item_color_quantities')->where('id', $row->id)->update(['delivered_quantity' => 6]), 4025);
        $this->rejects(fn () => DB::table('customers')->where('id', $order->customer_id)->delete(), 1451);
        $this->rejects(fn () => DB::table('orders')->insert(['order_number' => 'invalid-fk', 'customer_id' => 9999999, 'total_quantity' => 1]), 1452);
        $code = $item->product_code;
        DB::table('products')->where('id', $item->product_id)->delete();
        $this->assertNull($item->fresh()->product_id);
        $this->assertNull($item->fresh()->product_variant_id);
        $this->assertSame($code, $item->fresh()->product_code);
    }

    public function test_upgrade_preserves_existing_customer_duplicates_and_historical_delivery(): void
    {
        // Start immediately before per-color backfill and all WhatsApp migrations.
        $this->artisan('migrate:reset', ['--force' => true])->assertExitCode(0);
        $paths = glob(database_path('migrations/*.php'));
        $baseline = array_values(array_filter($paths, fn ($path) => basename($path) < '2026_09_22_100000'));
        $this->artisan('migrate', ['--path' => $baseline, '--realpath' => true, '--force' => true])->assertExitCode(0);
        $first = DB::table('customers')->insertGetId(['name' => 'قديم', 'phone' => '01001234567', 'whatsapp' => null]);
        DB::table('customers')->insert(['name' => 'Duplicate', 'phone' => '+20 100 123 4567', 'whatsapp' => '00201001234567']);
        $orderId = DB::table('orders')->insertGetId(['customer_id' => $first, 'order_number' => 'ORD-2026-00001', 'status' => 'partially_delivered', 'total_quantity' => 10]);
        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_code' => 'LEGACY', 'product_name' => 'منتج قديم',
            'color' => 'Black، White', 'size' => null, 'quantity' => 10,
            'requested_quantity' => 5, 'color_count' => 2, 'delivered_quantity' => 3,
            'price_visibility' => 'request_price', 'unit_price' => null,
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertSame(2, DB::table('customers')->where('phone_normalized', '201001234567')->count());
        $this->assertSame($first, Customer::matchOrCreate(['name' => 'Matched', 'phone' => '00201001234567'])->id);
        $this->assertSame(1, (int) DB::table('order_items')->find($itemId)->unallocated_delivered_quantity);
        $this->assertSame(2, (int) DB::table('order_item_color_quantities')->where('order_item_id', $itemId)->sum('delivered_quantity'));
        $this->assertSame(10, (int) DB::table('order_item_color_quantities')->where('order_item_id', $itemId)->sum('requested_quantity'));
        $this->assertSame('منتج قديم', DB::table('order_items')->find($itemId)->product_name);
        // Roll back only the newly approved B1 batch, preserving historical rows.
        $this->artisan('migrate:rollback', ['--step' => 3, '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasColumn('customers', 'phone_normalized'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertSame(2, DB::table('customers')->where('phone_normalized', '201001234567')->count());
    }

    public function test_two_process_customer_creation_matches_one_normalized_identity(): void
    {
        $key = hash('sha256', PhoneNumber::normalize('01001234567'));
        DB::table('customer_phone_locks')->insert(['phone_key' => $key]);
        DB::beginTransaction();
        DB::table('customer_phone_locks')->where('phone_key', $key)->lockForUpdate()->first();
        $workers = $this->startPair('customer', 0, ['01001234567', '+20 100 123 4567']);
        DB::commit();
        $results = $this->finishPair($workers);
        $this->assertSame($results[0], $results[1]);
        $this->assertStringContainsString('OK:', $results[0]);
        $this->assertSame(1, Customer::where('phone_normalized', '201001234567')->count());
    }

    public function test_two_process_delivery_rejects_stale_submission_without_double_increment(): void
    {
        [$order, $item] = $this->orderFixture();
        $order->confirm();
        $row = $item->colorQuantities()->firstOrFail();
        DB::beginTransaction();
        DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
        $workers = $this->startPair('delivery', $order->id, [(string) $row->id, (string) $row->id]);
        DB::commit();
        $results = $this->finishPair($workers);
        sort($results);
        $this->assertSame(['OK', 'REJECTED'], $results);
        $this->assertSame(2, $row->fresh()->delivered_quantity);
        $this->assertSame(2, $item->fresh()->delivered_quantity);
        $this->assertSame('partially_delivered', $order->fresh()->status->value);
    }

    public function test_two_process_order_transition_allows_only_one_confirmation(): void
    {
        [$order] = $this->orderFixture();
        DB::beginTransaction();
        DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
        $workers = $this->startPair('confirm', $order->id, ['', '']);
        DB::commit();
        $results = $this->finishPair($workers);
        sort($results);
        $this->assertSame(['OK', 'REJECTED'], $results);
        $this->assertSame('confirmed', $order->fresh()->status->value);
    }

    public function test_two_process_duplicate_order_number_has_one_winner(): void
    {
        $customer = Customer::factory()->create();
        $workers = $this->startPair('number', $customer->id, ['ORD-2026-99999', 'ORD-2026-99999']);
        $results = $this->finishPair($workers, [0, 2]);
        sort($results);
        $this->assertSame(['OK', 'SQL_ERROR:1062'], $results);
        $this->assertSame(1, Order::where('order_number', 'ORD-2026-99999')->count());
    }

    public function test_two_process_checkout_creates_complete_distinct_orders_for_one_customer(): void
    {
        $variant = ProductVariant::factory()->for(Product::factory()->create(['size_enabled' => true]))->create(['color' => 'Black', 'size' => '41']);
        $key = hash('sha256', PhoneNumber::normalize('01008880000'));
        DB::table('customer_phone_locks')->insert(['phone_key' => $key]);
        DB::beginTransaction();
        DB::table('customer_phone_locks')->where('phone_key', $key)->lockForUpdate()->first();
        $workers = $this->startPair('checkout', $variant->id, ['01008880000', '+20 100 888 0000']);
        DB::commit();
        $results = $this->finishPair($workers);
        $this->assertNotSame($results[0], $results[1]);
        $this->assertStringContainsString('OK:', $results[0]);
        $this->assertStringContainsString('OK:', $results[1]);
        $this->assertSame(1, Customer::where('phone_normalized', '201008880000')->count());
        $this->assertSame(2, Order::count());
        $this->assertSame(2, Order::distinct()->count('order_number'));
        $this->assertSame(4, (int) OrderItem::sum('quantity'));
        $this->assertSame(2, OrderItem::where('product_code', $variant->product->product_code)->count());
    }

    private function orderFixture(): array
    {
        $variant = ProductVariant::factory()->for(Product::factory()->create(['size_enabled' => true]))->create(['color' => 'Black', 'size' => '41']);
        $order = Order::create(['customer_id' => Customer::factory()->create()->id, 'order_number' => Order::nextOrderNumber(), 'total_quantity' => 5]);
        $item = $order->items()->create(OrderItem::snapshotFromVariant($variant) + ['quantity' => 5, 'requested_quantity' => 5]);

        return [$order, $item];
    }

    private function rejects(callable $query, int $expected): void
    {
        try {
            $query();
            $this->fail('Database did not reject invalid data.');
        } catch (QueryException $exception) {
            $this->assertSame($expected, (int) $exception->errorInfo[1]);
        }
    }

    private function startPair(string $action, int $id, array $arguments): array
    {
        $environment = [
            'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => 'bootstrap/cache/rel-b1-unavailable.php',
            'REL_B1_DATABASE_VERIFICATION' => '1', 'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => DatabaseGuard::DATABASE, 'DB_URL' => '',
            'WHATSAPP_ENABLED' => 'false', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ];
        $workers = array_map(function ($argument) use ($action, $id, $environment) {
            $ready = sys_get_temp_dir().'/rel-b1-'.bin2hex(random_bytes(12)).'.ready';
            $process = new Process([PHP_BINARY, __DIR__.'/concurrent-worker.php', $action, (string) $id, $argument, $ready], base_path(), $environment);
            $process->setTimeout(25);
            $process->start();

            return ['process' => $process, 'ready' => $ready];
        }, $arguments);
        foreach ($workers as $worker) {
            $deadline = microtime(true) + 10;
            while (! is_file($worker['ready']) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($worker['ready'], 'Worker did not reach database operation.');
            unlink($worker['ready']);
        }

        return array_column($workers, 'process');
    }

    private function finishPair(array $workers, array $exitCodes = [0]): array
    {
        return array_map(function (Process $worker) use ($exitCodes) {
            $worker->wait();
            $this->assertContains($worker->getExitCode(), $exitCodes, 'Worker failure: '.$worker->getErrorOutput());

            return trim(str_replace('READY', '', $worker->getOutput()));
        }, $workers);
    }
}
