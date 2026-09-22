<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketing consent tracking on the customer record. Existing customers
     * default to `unknown` (not eligible); no consent-history table exists.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('whatsapp_marketing_status', 20)->default('unknown');
            $table->timestamp('whatsapp_marketing_opted_in_at')->nullable();
            $table->timestamp('whatsapp_marketing_opted_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_marketing_status',
                'whatsapp_marketing_opted_in_at',
                'whatsapp_marketing_opted_out_at',
            ]);
        });
    }
};
