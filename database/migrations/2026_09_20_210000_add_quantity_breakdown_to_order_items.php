<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('requested_quantity')->default(1)->after('size');
            $table->unsignedSmallInteger('color_count')->default(1)->after('requested_quantity');
        });

        DB::table('order_items')
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    DB::table('order_items')
                        ->where('id', $item->id)
                        ->update([
                            // Preserve historical totals. The per-color rule applies
                            // only to orders created after this migration.
                            'requested_quantity' => (int) $item->quantity,
                            'color_count' => 1,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['requested_quantity', 'color_count']);
        });
    }
};
