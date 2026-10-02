<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `exported` is no longer a lifecycle status (export is an action).
     * Exporting did not prove physical delivery and no delivered-quantity
     * history exists, so exported orders are preserved as `confirmed`.
     * Delivered quantities stay at their schema default (0).
     *
     * Irreversible: the original exported distinction cannot be
     * reconstructed. A database backup is mandatory before running this.
     */
    public function up(): void
    {
        $affected = DB::table('orders')
            ->where('status', 'exported')
            ->update(['status' => 'confirmed']);

        if ($affected > 0) {
            echo "Normalized {$affected} exported order(s) to confirmed.".PHP_EOL;
        }
    }

    public function down(): void
    {
        // Intentionally irreversible; restore from backup if required.
    }
};
