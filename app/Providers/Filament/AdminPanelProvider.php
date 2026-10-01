<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\ProductionRequirementsWidget;
use App\Http\Controllers\OrderPrintController;
use App\Http\Controllers\WhatsAppQrController;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
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
            ->brandName(fn (): string => __('admin.brand'))
            ->brandLogo('/Logo.png?v=20261001')
            ->brandLogoHeight('3rem')
            ->favicon('/favicon.png?v=20261001')
            ->font(
                'IBM Plex Sans Arabic',
                'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap',
            )
            ->colors([
                'primary' => [
                    50 => '#eff6ff',
                    100 => '#dbeafe',
                    200 => '#bfdbfe',
                    300 => '#93c5fd',
                    400 => '#60a5fa',
                    500 => '#3b82f6',
                    600 => '#2563eb',
                    700 => '#1d4ed8',
                    800 => '#1e40af',
                    900 => '#1e3a8a',
                    950 => '#172554',
                ],
            ])
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups([
                NavigationGroup::make()->label(fn (): string => __('admin.navigation.operations')),
                NavigationGroup::make()->label(fn (): string => __('admin.navigation.catalog')),
                NavigationGroup::make()->label(fn (): string => __('admin.navigation.customers')),
                NavigationGroup::make()->label(fn (): string => __('admin.navigation.whatsapp')),
                NavigationGroup::make()->label(fn (): string => __('admin.navigation.settings')),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.components.locale-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => '<div class="mt-4 flex justify-center">'.view('filament.components.locale-switcher')->render().'</div>',
            )
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
                Route::get('/whatsapp/qr', WhatsAppQrController::class)->name('whatsapp.qr');
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
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
