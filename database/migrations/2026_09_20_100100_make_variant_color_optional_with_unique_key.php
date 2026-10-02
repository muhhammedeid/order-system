<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Makes variant/snapshot colors optional for color-disabled products,
     * mirroring the size migration (2026_09_19_100100).
     *
     * A plain nullable composite unique in MySQL/MariaDB allows duplicate
     * rows containing NULL, so uniqueness is enforced through a generated
     * `color_key` column (COALESCE(color, '')) indexed together with
     * product_id and size_key. Indexes on virtual generated columns are
     * supported by MariaDB (10.11), MySQL and TiDB.
     *
     * Statement order matters: both color columns become nullable before
     * color_key is generated, because TiDB rejects modifying a column while
     * a generated column depends on it. The new unique index is created
     * before the old one is dropped so the product_id foreign key is always
     * backed by a unique index.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('color')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('color')->nullable()->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('color_key')->virtualAs("coalesce(color, '')")->after('color');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'color_key', 'size_key']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'color', 'size_key']);
        });
    }

    /**
     * Not strictly reversible: NULL colors are normalized to empty strings
     * because the previous schema required a non-null color value.
     */
    public function down(): void
    {
        DB::table('product_variants')->whereNull('color')->update(['color' => '']);
        DB::table('order_items')->whereNull('color')->update(['color' => '']);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'color', 'size_key']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('color')->nullable(false)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'color_key', 'size_key']);
            $table->dropColumn('color_key');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('color')->nullable(false)->change();
        });
    }
};
