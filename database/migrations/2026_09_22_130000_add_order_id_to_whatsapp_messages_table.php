<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional order association for individual messages. Conversations stay
     * customer/chat scoped, so one conversation can naturally hold messages
     * for several orders. Existing history keeps order_id = null, and the
     * messages survive an order removal because the FK nulls out.
     */
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('conversation_id')
                ->constrained('orders')->nullOnDelete();

            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'occurred_at']);
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
