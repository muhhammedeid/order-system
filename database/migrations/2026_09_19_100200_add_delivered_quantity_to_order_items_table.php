<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks delivered quantities per order item. The lower bound is the
     * unsigned column itself; the upper bound is enforced by a database
     * CHECK constraint on the production MariaDB/MySQL connection and by
     * application-level validation everywhere.
     *
     * SQLite cannot add CHECK constraints to an existing table, so the
     * constraint is skipped there (the PHPUnit environment) and enforced
     * by the model instead. Production MariaDB must always have it.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('delivered_quantity')->default(0)->after('quantity');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE `order_items` ADD CONSTRAINT `order_items_delivered_within_quantity_check` CHECK (`delivered_quantity` <= `quantity`)'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            if (DB::connection()->isMaria()) {
                DB::statement('ALTER TABLE `order_items` DROP CONSTRAINT `order_items_delivered_within_quantity_check`');
            } else {
                DB::statement('ALTER TABLE `order_items` DROP CHECK `order_items_delivered_within_quantity_check`');
            }
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('delivered_quantity');
        });
    }
};
