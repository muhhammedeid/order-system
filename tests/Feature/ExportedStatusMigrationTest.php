<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExportedStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function normalizeMigration(): object
    {
        return require database_path('migrations/2026_09_19_100300_normalize_exported_order_status.php');
    }

    public function test_exported_is_no_longer_a_lifecycle_status(): void
    {
        $this->assertNull(OrderStatus::tryFrom('exported'));
    }

    public function test_legacy_exported_orders_are_preserved_as_confirmed_with_zero_delivered_quantity(): void
    {
        $customer = Customer::factory()->create();

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-2026-90001',
            'customer_id' => $customer->id,
            'status' => 'exported',
            'total_quantity' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_code' => 'LEG-1',
            'product_name' => 'Legacy Shoe',
            'color' => 'Black',
            'size' => '41',
            'quantity' => 3,
            'delivered_quantity' => 0,
            'unit_price' => 100,
            'price_visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->normalizeMigration()->up();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'confirmed']);
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'delivered_quantity' => 0]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }
}
