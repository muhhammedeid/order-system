<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('color_enabled', false)
            ->where('size_enabled', true)
            ->update(['size_enabled' => false]);
    }

    public function down(): void
    {
        // The previous invalid combination cannot be reconstructed safely.
    }
};
