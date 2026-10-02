<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItemColorQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The historical migration must attribute already recorded deliveries to
 * the snapshot colors without inventing colors, and must keep anything that
 * cannot be attributed exactly as a legacy unallocated quantity.
 */
class OrderItemColorQuantityBackfillTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inserts a legacy order item without triggering the color-row hooks.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function legacyItem(array $attributes): int
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'total_quantity' => 0,
        ]);

        return (int) DB::table('order_items')->insertGetId([
            'order_id' => $order->id,
            'product_id' => null,
            'product_variant_id' => null,
            'product_code' => 'LEG-1',
            'product_name' => 'Legacy Line',
            'color' => $attributes['color'] ?? null,
            'size' => null,
            'requested_quantity' => $attributes['requested_quantity'],
            'color_count' => $attributes['color_count'],
            'quantity' => $attributes['quantity'],
            'delivered_quantity' => $attributes['delivered_quantity'],
            'unallocated_delivered_quantity' => 0,
            'unit_price' => null,
            'price_visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_09_22_100000_create_order_item_color_quantities_table.php');

        $method = new \ReflectionMethod($migration, 'backfillColorQuantities');
        $method->setAccessible(true);
        $method->invoke($migration);
    }

    /**
     * @return array<string, array{requested: int, delivered: int}>
     */
    private function colorRows(int $itemId): array
    {
        return OrderItemColorQuantity::query()
            ->where('order_item_id', $itemId)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (OrderItemColorQuantity $row): array => [
                $row->color => [
                    'requested' => $row->requested_quantity,
                    'delivered' => $row->delivered_quantity,
                ],
            ])
            ->all();
    }

    public function test_single_color_history_receives_its_whole_delivered_quantity(): void
    {
        $itemId = $this->legacyItem([
            'color' => 'Black',
            'requested_quantity' => 8,
            'color_count' => 1,
            'quantity' => 8,
            'delivered_quantity' => 3,
        ]);

        $this->runBackfill();

        $this->assertSame([
            'Black' => ['requested' => 8, 'delivered' => 3],
        ], $this->colorRows($itemId));

        $this->assertSame(0, (int) DB::table('order_items')->where('id', $itemId)->value('unallocated_delivered_quantity'));
    }

    public function test_evenly_divisible_multi_color_history_is_split_equally(): void
    {
        $itemId = $this->legacyItem([
            'color' => 'Black، White',
            'requested_quantity' => 10,
            'color_count' => 2,
            'quantity' => 20,
            'delivered_quantity' => 8,
        ]);

        $this->runBackfill();

        $this->assertSame([
            'Black' => ['requested' => 10, 'delivered' => 4],
            'White' => ['requested' => 10, 'delivered' => 4],
        ], $this->colorRows($itemId));

        $this->assertSame(0, (int) DB::table('order_items')->where('id', $itemId)->value('unallocated_delivered_quantity'));
    }

    public function test_fully_delivered_multi_color_history_is_attributed_completely(): void
    {
        $itemId = $this->legacyItem([
            'color' => 'Black, White, Beige',
            'requested_quantity' => 10,
            'color_count' => 3,
            'quantity' => 30,
            'delivered_quantity' => 30,
        ]);

        $this->runBackfill();

        $this->assertSame([
            'Black' => ['requested' => 10, 'delivered' => 10],
            'White' => ['requested' => 10, 'delivered' => 10],
            'Beige' => ['requested' => 10, 'delivered' => 10],
        ], $this->colorRows($itemId));

        $this->assertSame(0, (int) DB::table('order_items')->where('id', $itemId)->value('unallocated_delivered_quantity'));
    }

    public function test_non_divisible_multi_color_history_keeps_the_remainder_unallocated(): void
    {
        $itemId = $this->legacyItem([
            'color' => 'Black، White، Beige',
            'requested_quantity' => 10,
            'color_count' => 3,
            'quantity' => 30,
            'delivered_quantity' => 8,
        ]);

        $this->runBackfill();

        // floor(8 / 3) = 2 per color; the two remaining pieces are never
        // attributed to an arbitrary color.
        $this->assertSame([
            'Black' => ['requested' => 10, 'delivered' => 2],
            'White' => ['requested' => 10, 'delivered' => 2],
            'Beige' => ['requested' => 10, 'delivered' => 2],
        ], $this->colorRows($itemId));

        $this->assertSame(2, (int) DB::table('order_items')->where('id', $itemId)->value('unallocated_delivered_quantity'));
    }

    public function test_items_without_a_parseable_color_keep_their_requested_quantity(): void
    {
        $itemId = $this->legacyItem([
            'color' => null,
            'requested_quantity' => 4,
            'color_count' => 1,
            'quantity' => 4,
            'delivered_quantity' => 0,
        ]);

        $this->runBackfill();

        $this->assertSame([
            'غير محدد' => ['requested' => 4, 'delivered' => 0],
        ], $this->colorRows($itemId));
    }

    public function test_legacy_line_with_a_multi_color_snapshot_and_single_color_count_keeps_one_row(): void
    {
        // Rows normalized before the quantity breakdown kept color_count = 1
        // while the snapshot could already contain several colors. Splitting
        // them would invent per-color quantities that double the physical
        // total, so they stay as one legacy line with its raw value.
        $itemId = $this->legacyItem([
            'color' => 'Black، White',
            'requested_quantity' => 8,
            'color_count' => 1,
            'quantity' => 8,
            'delivered_quantity' => 3,
        ]);

        $this->runBackfill();

        $this->assertSame([
            'Black، White' => ['requested' => 8, 'delivered' => 3],
        ], $this->colorRows($itemId));

        $this->assertSame(
            8,
            (int) DB::table('order_item_color_quantities')->where('order_item_id', $itemId)->sum('requested_quantity'),
            'The per-color requested quantities must never exceed the stored physical total.',
        );

        $this->assertSame(0, (int) DB::table('order_items')->where('id', $itemId)->value('unallocated_delivered_quantity'));
    }

    public function test_case_variant_duplicate_colors_never_break_the_unique_index(): void
    {
        // MySQL/MariaDB unique indexes are case-insensitive, so "Black" and
        // "black" cannot become two rows.
        $itemId = $this->legacyItem([
            'color' => 'Black, black',
            'requested_quantity' => 5,
            'color_count' => 2,
            'quantity' => 10,
            'delivered_quantity' => 4,
        ]);

        $this->runBackfill();

        $rows = $this->colorRows($itemId);

        $this->assertCount(1, $rows);
        $this->assertSame(10, $rows['Black, black']['requested']);
        $this->assertSame(4, $rows['Black, black']['delivered']);
    }

    public function test_backfill_never_uses_the_products_current_colors(): void
    {
        $itemId = $this->legacyItem([
            'color' => 'Black، White',
            'requested_quantity' => 5,
            'color_count' => 2,
            'quantity' => 10,
            'delivered_quantity' => 0,
        ]);

        $this->runBackfill();

        $this->assertSame(['Black', 'White'], array_keys($this->colorRows($itemId)));
    }
}
