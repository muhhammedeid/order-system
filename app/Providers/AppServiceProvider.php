<?php

namespace App\Providers;

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
        //
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
