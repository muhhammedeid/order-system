<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbox customer matching compares verified WhatsApp numbers against the
     * customer phone and WhatsApp fields. The phone column is already indexed;
     * this adds the missing index for the WhatsApp column.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['whatsapp']);
        });
    }
};
