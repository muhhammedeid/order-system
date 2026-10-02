<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the per-product optional-size flag. Products that already have
     * variants are considered size-enabled so existing behavior is preserved;
     * new products default to size selection disabled.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('size_enabled')->default(false)->after('active');
        });

        DB::table('products')
            ->whereIn('id', DB::table('product_variants')->select('product_id')->distinct())
            ->update(['size_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_enabled');
        });
    }
};
