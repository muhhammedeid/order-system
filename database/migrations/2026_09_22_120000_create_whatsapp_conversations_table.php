<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One WhatsApp conversation per provider chat id. The provider chat id is
     * the stable technical key; Laravel never stores provider session/auth
     * state, QR values or raw webhook payloads.
     */
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_chat_id', 64)->unique();
            $table->string('resolved_phone', 20)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 160)->nullable();
            $table->string('last_message_direction', 8)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index('customer_id');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
