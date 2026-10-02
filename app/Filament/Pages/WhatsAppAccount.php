<?php

namespace App\Filament\Pages;

use App\Contracts\WhatsAppGateway;
use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\SessionView;
use App\Support\WhatsApp\WhatsAppException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp account and session operations for the configured WAHA session.
 *
 * The page never talks to WAHA directly: every provider call goes through
 * App\Contracts\WhatsAppGateway. The configured session from
 * config('whatsapp.session') is the single source of truth; no account
 * records are persisted in Laravel and no arbitrary session can be operated.
 */
class WhatsAppAccount extends Page
{
    protected string $view = 'filament.pages.whatsapp-account';

    protected static ?string $slug = 'whatsapp-account';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 10;

    public bool $enabled = false;

    public bool $serviceHealthy = false;

    public ?string $sessionName = null;

    public ?string $rawStatus = null;

    public ?string $identityName = null;

    public ?string $identityPhone = null;

    public ?string $lastCheckedAt = null;

    public int $qrVersion = 0;

    public function mount(): void
    {
        $this->sessionName = (string) config('whatsapp.session', 'default');

        $this->refreshStatus();
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.whatsapp_account');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.whatsapp');
    }

    public function getTitle(): string
    {
        return __('admin.navigation.whatsapp_account');
    }

    /**
     * On-demand provider read. Never persisted and never polled.
     */
    public function refreshStatus(): void
    {
        $gateway = $this->gateway();

        $this->enabled = $gateway->enabled();
        $this->qrVersion++;
        $this->lastCheckedAt = now()->format('Y-m-d H:i');

        if (! $this->enabled) {
            $this->serviceHealthy = false;
            $this->rawStatus = null;
            $this->identityName = null;
            $this->identityPhone = null;

            return;
        }

        $this->serviceHealthy = $gateway->health();

        $session = null;

        if ($this->serviceHealthy) {
            try {
                $session = $gateway->session((string) $this->sessionName);
            } catch (WhatsAppException $exception) {
                $this->serviceHealthy = false;
                $this->logProviderFailure('refresh status', $exception);
            }
        }

        $this->rawStatus = $session?->status;
        $this->identityName = SessionView::identityName($session?->me);
        $this->identityPhone = SessionView::identityPhone($session?->me);
    }

    /**
     * Runs an admin action after re-checking the live provider state.
     */
    public function perform(string $action): void
    {
        $gateway = $this->gateway();

        if (! $gateway->enabled()) {
            Notification::make()
                ->title(__('admin.whatsapp.notifications.disabled'))
                ->warning()
                ->send();

            return;
        }

        $this->refreshStatus();

        if (! $this->sessionView()->allows($action)) {
            Notification::make()
                ->title(__('admin.whatsapp.notifications.action_failed'))
                ->body(__('admin.whatsapp.notifications.state_changed'))
                ->warning()
                ->send();

            return;
        }

        try {
            match ($action) {
                SessionView::ACTION_START => $gateway->startSession((string) $this->sessionName),
                SessionView::ACTION_STOP => $gateway->stopSession((string) $this->sessionName),
                SessionView::ACTION_RESTART => $gateway->restartSession((string) $this->sessionName),
                SessionView::ACTION_LOGOUT => $gateway->logoutSession((string) $this->sessionName),
                SessionView::ACTION_CREATE_START => $this->createAndStart($gateway),
                default => null,
            };
        } catch (WhatsAppException $exception) {
            $this->logProviderFailure($action, $exception);

            $this->refreshStatus();

            Notification::make()
                ->title(__('admin.whatsapp.notifications.action_failed'))
                ->body(__('admin.whatsapp.notifications.action_failed_body'))
                ->danger()
                ->send();

            return;
        }

        $this->refreshStatus();

        Notification::make()
            ->title(__('admin.whatsapp.notifications.action_succeeded'))
            ->body($this->sessionView()->label())
            ->success()
            ->send();
    }

    public function sessionView(): SessionView
    {
        $session = ($this->sessionName !== null && $this->rawStatus !== null)
            ? new SessionState(name: $this->sessionName, status: $this->rawStatus)
            : null;

        return SessionView::make($session, $this->enabled, $this->serviceHealthy);
    }

    public function allows(string $action): bool
    {
        return $this->sessionView()->allows($action);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('admin.whatsapp.actions.refresh'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->refreshStatus()),

            Action::make('refreshQr')
                ->label(__('admin.whatsapp.actions.refresh_qr'))
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->visible(fn (): bool => $this->allows(SessionView::ACTION_REFRESH_QR))
                ->action(fn () => $this->refreshStatus()),

            Action::make('createStart')
                ->label(__('admin.whatsapp.actions.create_start'))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->visible(fn (): bool => $this->allows(SessionView::ACTION_CREATE_START))
                ->action(fn () => $this->perform(SessionView::ACTION_CREATE_START)),

            Action::make('start')
                ->label(__('admin.whatsapp.actions.start'))
                ->icon(Heroicon::OutlinedPlayCircle)
                ->visible(fn (): bool => $this->allows(SessionView::ACTION_START))
                ->action(fn () => $this->perform(SessionView::ACTION_START)),

            Action::make('restart')
                ->label(__('admin.whatsapp.actions.restart'))
                ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->color('warning')
                ->visible(fn (): bool => $this->allows(SessionView::ACTION_RESTART))
                ->action(fn () => $this->perform(SessionView::ACTION_RESTART)),

            Action::make('stop')
                ->label(__('admin.whatsapp.actions.stop'))
                ->icon(Heroicon::OutlinedStopCircle)
                ->color('warning')
                ->visible(fn (): bool => $this->allows(SessionView::ACTION_STOP))
                ->action(fn () => $this->perform(SessionView::ACTION_STOP)),

            Action::make('logout')
                ->label(__('admin.whatsapp.actions.logout'))
                ->icon(Heroicon::OutlinedPower)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('admin.whatsapp.confirm.logout_heading'))
                ->modalDescription(__('admin.whatsapp.confirm.logout_description'))
                ->modalSubmitActionLabel(__('admin.whatsapp.confirm.logout_confirm'))
                ->visible(fn (): bool => $this->allows(SessionView::ACTION_LOGOUT))
                ->action(fn () => $this->perform(SessionView::ACTION_LOGOUT)),
        ];
    }

    /**
     * Creates only the session defined by application configuration.
     */
    private function createAndStart(WhatsAppGateway $gateway): void
    {
        $name = (string) $this->sessionName;

        $gateway->createSession($name, [
            [
                'url' => url('/'.config('whatsapp.webhook.path')),
                'events' => ['message', 'message.any', 'message.ack', 'session.status'],
            ],
        ]);

        $gateway->startSession($name);
    }

    private function gateway(): WhatsAppGateway
    {
        return app(WhatsAppGateway::class);
    }

    private function logProviderFailure(string $action, WhatsAppException $exception): void
    {
        // Sanitized context only: no credentials, no QR data, no payloads.
        Log::warning('WhatsApp provider call failed', [
            'action' => $action,
            'session' => $this->sessionName,
            'reason' => $exception->getMessage(),
        ]);
    }
}
