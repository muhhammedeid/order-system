<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_dispatch_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('whatsapp_campaigns')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->constrained('whatsapp_templates')->restrictOnDelete();
            $table->foreignId('dispatch_id')->nullable()->constrained('whatsapp_dispatches')->restrictOnDelete();
            $table->text('product_url')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'customer_id']);
            $table->unique('dispatch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
