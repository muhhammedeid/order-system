<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Makes variant/snapshot sizes optional for unsized products.
     *
     * A plain nullable composite unique in MySQL/MariaDB allows duplicate
     * rows containing NULL, so uniqueness is enforced through a generated
     * `size_key` column (COALESCE(size, '')) which is indexed together with
     * product_id and color. Indexes on virtual generated columns are
     * supported by MariaDB (10.11), MySQL and TiDB.
     *
     * Statement order matters: both size columns become nullable before
     * size_key is generated, because TiDB rejects modifying a column while
     * a generated column depends on it.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('size')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('size')->nullable()->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('size_key')->virtualAs("coalesce(size, '')")->after('size');
        });

        // The new unique index is created before the old one is dropped so
        // the product_id foreign key is always backed by a unique index.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'color', 'size_key']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'color', 'size']);
        });
    }

    /**
     * Not strictly reversible: NULL sizes are normalized to empty strings
     * because the previous schema required a non-null size value.
     */
    public function down(): void
    {
        DB::table('product_variants')->whereNull('size')->update(['size' => '']);
        DB::table('order_items')->whereNull('size')->update(['size' => '']);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'color', 'size']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('size')->nullable(false)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'color', 'size_key']);
            $table->dropColumn('size_key');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('size')->nullable(false)->change();
        });
    }
};
