<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the per-product optional-color flag, symmetric to `size_enabled`.
     * Color selection defaults to enabled so every existing product keeps its
     * current behavior; Admin can disable it per product.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('color_enabled')->default(true)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('color_enabled');
        });
    }
};
