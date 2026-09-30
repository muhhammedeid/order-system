<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key')->unique();
            $table->string('kind', 30);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->string('recipient_phone', 30);
            $table->text('body');
            $table->text('media_url')->nullable();
            $table->string('media_mimetype')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('resolution_attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->foreignId('whatsapp_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_dispatches');
    }
};
