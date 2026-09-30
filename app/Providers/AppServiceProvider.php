<?php

namespace App\Providers;

use App\Contracts\WhatsAppGateway;
use App\Services\WhatsApp\UnavailableGateway;
use App\Services\WhatsApp\WahaGateway;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppGateway::class, function ($app): WhatsAppGateway {
            $driver = config('whatsapp.driver') ?? config('whatsapp.provider', 'waha');

            return $app->make($driver === 'waha' ? WahaGateway::class : UnavailableGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale('en'));
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('en'));
    }
}
