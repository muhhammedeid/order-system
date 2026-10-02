<?php

namespace App\Support\WhatsApp;

/**
 * Maps the raw provider session status to the business-facing admin state,
 * its label/color keys, the actions allowed in that state and the operator
 * guidance. Raw provider statuses are preserved for diagnostics only and are
 * never used as the primary UI label.
 *
 * Resolution order: disabled -> service unavailable -> not created -> raw
 * status mapping. Any unknown raw status falls back to `attention`.
 */
class SessionView
{
    public const STATE_CONNECTED = 'connected';

    public const STATE_CONNECTING = 'connecting';

    public const STATE_QR_REQUIRED = 'qr_required';

    public const STATE_STOPPED = 'stopped';

    public const STATE_ATTENTION = 'attention';

    public const STATE_VERIFICATION_REQUIRED = 'verification_required';

    public const STATE_NOT_CREATED = 'not_created';

    public const STATE_DISABLED = 'disabled';

    public const STATE_SERVICE_UNAVAILABLE = 'service_unavailable';

    public const ACTION_REFRESH = 'refresh';

    public const ACTION_REFRESH_QR = 'refresh_qr';

    public const ACTION_START = 'start';

    public const ACTION_STOP = 'stop';

    public const ACTION_RESTART = 'restart';

    public const ACTION_LOGOUT = 'logout';

    public const ACTION_CREATE_START = 'create_start';

    /**
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'WORKING' => self::STATE_CONNECTED,
        'STARTING' => self::STATE_CONNECTING,
        'SCAN_QR_CODE' => self::STATE_QR_REQUIRED,
        'STOPPED' => self::STATE_STOPPED,
        'FAILED' => self::STATE_ATTENTION,
        'PASSKEY_REQUIRED' => self::STATE_VERIFICATION_REQUIRED,
        'PASSKEY_CONFIRMATION_REQUIRED' => self::STATE_VERIFICATION_REQUIRED,
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const STATE_ACTIONS = [
        self::STATE_CONNECTED => [
            self::ACTION_REFRESH,
            self::ACTION_RESTART,
            self::ACTION_STOP,
            self::ACTION_LOGOUT,
        ],
        self::STATE_CONNECTING => [
            self::ACTION_REFRESH,
        ],
        self::STATE_QR_REQUIRED => [
            self::ACTION_REFRESH_QR,
            self::ACTION_STOP,
        ],
        self::STATE_STOPPED => [
            self::ACTION_START,
            self::ACTION_REFRESH,
        ],
        self::STATE_ATTENTION => [
            self::ACTION_REFRESH,
            self::ACTION_RESTART,
            self::ACTION_LOGOUT,
        ],
        self::STATE_VERIFICATION_REQUIRED => [
            self::ACTION_REFRESH,
            self::ACTION_RESTART,
        ],
        self::STATE_NOT_CREATED => [
            self::ACTION_CREATE_START,
            self::ACTION_REFRESH,
        ],
        self::STATE_DISABLED => [
            self::ACTION_REFRESH,
        ],
        self::STATE_SERVICE_UNAVAILABLE => [
            self::ACTION_REFRESH,
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const STATE_COLORS = [
        self::STATE_CONNECTED => 'success',
        self::STATE_CONNECTING => 'info',
        self::STATE_QR_REQUIRED => 'warning',
        self::STATE_STOPPED => 'gray',
        self::STATE_ATTENTION => 'danger',
        self::STATE_VERIFICATION_REQUIRED => 'danger',
        self::STATE_NOT_CREATED => 'gray',
        self::STATE_DISABLED => 'gray',
        self::STATE_SERVICE_UNAVAILABLE => 'danger',
    ];

    private function __construct(
        public readonly string $state,
        public readonly ?string $rawStatus,
        public readonly bool $enabled,
        public readonly bool $serviceHealthy,
    ) {}

    public static function make(?SessionState $session, bool $enabled, bool $serviceHealthy): self
    {
        if (! $enabled) {
            return new self(self::STATE_DISABLED, null, false, $serviceHealthy);
        }

        $rawStatus = $session?->status;

        if (! $serviceHealthy) {
            return new self(self::STATE_SERVICE_UNAVAILABLE, $rawStatus, true, false);
        }

        if ($session === null) {
            return new self(self::STATE_NOT_CREATED, null, true, true);
        }

        $state = self::STATUS_MAP[$rawStatus] ?? self::STATE_ATTENTION;

        return new self($state, $rawStatus, true, true);
    }

    public function allows(string $action): bool
    {
        return in_array($action, self::STATE_ACTIONS[$this->state] ?? [], true);
    }

    public function color(): string
    {
        return self::STATE_COLORS[$this->state] ?? 'gray';
    }

    public function isConnected(): bool
    {
        return $this->state === self::STATE_CONNECTED;
    }

    public function isQrVisible(): bool
    {
        return $this->state === self::STATE_QR_REQUIRED;
    }

    public function label(): string
    {
        return __('admin.whatsapp.states.'.$this->state);
    }

    public function guidance(): string
    {
        return __('admin.whatsapp.guidance.'.$this->state);
    }

    /**
     * Human-readable account name from the provider, when safely available.
     */
    public static function identityName(?array $me): ?string
    {
        $name = $me['pushName'] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /**
     * Phone number only when the provider exposes a regular `@c.us` id.
     * Hidden `@lid` identifiers are never converted into a phone number.
     */
    public static function identityPhone(?array $me): ?string
    {
        $id = $me['id'] ?? null;

        return is_string($id) ? PhoneNumber::fromChatId($id) : null;
    }
}
