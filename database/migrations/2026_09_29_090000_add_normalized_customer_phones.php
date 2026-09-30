<?php

use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone_normalized')->nullable()->index();
            $table->string('whatsapp_normalized')->nullable()->index();
        });
        Schema::create('customer_phone_locks', function (Blueprint $table): void {
            $table->string('phone_key', 64)->primary();
        });
        DB::table('customers')->orderBy('id')->chunkById(500, function ($customers): void {
            foreach ($customers as $customer) {
                DB::table('customers')->where('id', $customer->id)->update([
                    'phone_normalized' => PhoneNumber::normalize($customer->phone),
                    'whatsapp_normalized' => PhoneNumber::normalize($customer->whatsapp),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_phone_locks');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['phone_normalized']);
            $table->dropIndex(['whatsapp_normalized']);
            $table->dropColumn(['phone_normalized', 'whatsapp_normalized']);
        });
    }
};
