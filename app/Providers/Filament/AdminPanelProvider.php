<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\ProductionRequirementsWidget;
use App\Http\Controllers\OrderPrintController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('MAI SHOES')
            ->brandLogo('/favicon.svg')
            ->brandLogoHeight('2.25rem')
            ->favicon('/favicon.svg')
            ->font(
                'IBM Plex Sans Arabic',
                'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap',
            )
            ->colors([
                'primary' => [
                    50 => '#fdf3f3',
                    100 => '#fbe1e2',
                    200 => '#f8c1c4',
                    300 => '#f2969c',
                    400 => '#e75f68',
                    500 => '#d4343f',
                    600 => '#c91424',
                    700 => '#a50f1d',
                    800 => '#8f0808',
                    900 => '#741013',
                    950 => '#400508',
                ],
            ])
            ->spa()
            ->topNavigation()
            ->bootUsing(fn () => app()->setLocale('ar'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                OrderStatsWidget::class,
                ProductionRequirementsWidget::class,
                AccountWidget::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('/order-management/{order}/print', OrderPrintController::class)->name('orders.print');
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
