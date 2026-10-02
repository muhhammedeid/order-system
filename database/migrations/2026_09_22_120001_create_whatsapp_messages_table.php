<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversation messages. Provider identity is scoped to the conversation:
     * the WAHA identifier includes chat context, so uniqueness is
     * (conversation_id, provider_message_id) rather than the token alone.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('provider_message_id', 128)->nullable();
            $table->string('direction', 8);
            $table->string('message_type', 20);
            $table->text('body')->nullable();
            $table->string('status', 16)->nullable();
            $table->smallInteger('provider_ack')->nullable();
            $table->string('provider_ack_name', 32)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'provider_message_id']);
            $table->index(['conversation_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
