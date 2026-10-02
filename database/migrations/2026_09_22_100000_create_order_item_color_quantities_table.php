<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Color-level delivery tracking.
     *
     * Every ordered color of an order item gets one immutable snapshot row
     * carrying the requested and delivered quantity for that color only.
     * These rows become the authoritative source for partial delivery,
     * order status and Current Production Requirements; the aggregate
     * `order_items.delivered_quantity` is kept in sync for compatibility
     * (order history, totals, exports).
     *
     * Historical rows are backfilled without guessing:
     * - a single-color line takes its whole delivered quantity;
     * - a multi-color line distributes its delivered quantity equally when
     *   it divides exactly by the number of colors;
     * - anything that cannot be attributed exactly is stored as a legacy
     *   unallocated delivered quantity on the order item, which blocks
     *   further partial deliveries until an admin reconciles it.
     */
    public function up(): void
    {
        Schema::create('order_item_color_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('color');
            $table->unsignedInteger('requested_quantity');
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->timestamps();

            $table->unique(['order_item_id', 'color'], 'order_item_color_quantities_item_color_unique');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unallocated_delivered_quantity')->default(0)->after('delivered_quantity');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE `order_item_color_quantities` ADD CONSTRAINT `order_item_color_quantities_delivered_within_requested_check` CHECK (`delivered_quantity` <= `requested_quantity`)'
            );
        }

        $this->backfillColorQuantities();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            if (DB::connection()->isMaria()) {
                DB::statement('ALTER TABLE `order_item_color_quantities` DROP CONSTRAINT `order_item_color_quantities_delivered_within_requested_check`');
            } else {
                DB::statement('ALTER TABLE `order_item_color_quantities` DROP CHECK `order_item_color_quantities_delivered_within_requested_check`');
            }
        }

        Schema::dropIfExists('order_item_color_quantities');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unallocated_delivered_quantity');
        });
    }

    /**
     * Creates the color rows for every existing order item and distributes
     * the already recorded delivered quantity without inventing colors or
     * silently attributing deliveries.
     *
     * The sum of the per-color requested quantities always equals the
     * stored physical total: lines whose snapshot color list disagrees with
     * their recorded color count keep one legacy row with the raw snapshot
     * text, so no requested quantity is duplicated or lost.
     */
    private function backfillColorQuantities(): void
    {
        DB::transaction(function (): void {
            DB::table('order_items')
                ->orderBy('id')
                ->chunkById(200, function ($items): void {
                    foreach ($items as $item) {
                        $this->backfillItem($item);
                    }
                });
        });
    }

    private function backfillItem(object $item): void
    {
        $requested = max(1, (int) $item->requested_quantity);
        $colorCount = max(1, (int) $item->color_count);
        $colors = $this->snapshotColors($item);

        if (count($colors) !== $colorCount) {
            // Legacy or inconsistent snapshot: never split it into invented
            // colors. One row keeps the raw value and the full quantity.
            $raw = trim((string) $item->color);
            $colors = [$raw !== '' ? $raw : 'غير محدد'];
            $requested = max(1, (int) $item->quantity);
        }

        $delivered = max(0, (int) $item->delivered_quantity);
        $perColor = min(intdiv($delivered, count($colors)), $requested);

        $rows = [];
        $allocated = 0;

        foreach ($colors as $color) {
            $rows[] = [
                'order_item_id' => $item->id,
                'color' => $color,
                'requested_quantity' => $requested,
                'delivered_quantity' => $perColor,
                'created_at' => $item->created_at ?? now(),
                'updated_at' => $item->updated_at ?? now(),
            ];

            $allocated += $perColor;
        }

        DB::table('order_item_color_quantities')->insert($rows);

        $unallocated = $delivered - $allocated;

        if ($unallocated > 0) {
            DB::table('order_items')
                ->where('id', $item->id)
                ->update(['unallocated_delivered_quantity' => $unallocated]);
        }
    }

    /**
     * Ordered, unique color names from the immutable order-item snapshot.
     * Duplicates are matched case-insensitively because the unique index is
     * collation-dependent on MySQL/MariaDB. Items without a parseable color
     * keep their raw snapshot text, or a neutral placeholder, so no
     * requested quantity is ever dropped.
     *
     * @return array<int, string>
     */
    private function snapshotColors(object $item): array
    {
        $colors = preg_split('/[،,]+/u', (string) $item->color, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $colors = array_filter(array_map('trim', $colors));

        $unique = [];

        foreach ($colors as $color) {
            $unique[mb_strtolower($color)] ??= $color;
        }

        if ($unique !== []) {
            return array_values($unique);
        }

        $fallback = trim((string) $item->color);

        return [$fallback !== '' ? $fallback : 'غير محدد'];
    }
};
