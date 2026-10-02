<?php

namespace Tests\Fakes;

use App\Contracts\WhatsAppGateway;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\SentMessage;
use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\WhatsAppException;

class FakeWhatsAppGateway implements WhatsAppGateway
{
    public bool $enabled = true;

    public bool $healthy = true;

    public ?SessionState $session = null;

    public ?string $qr = null;

    public ?string $resolvedPhone = null;

    public ?WhatsAppException $throwOnQr = null;

    public ?WhatsAppException $throwOnAction = null;

    public ?\Throwable $throwOnResolve = null;

    public ?WhatsAppException $throwOnCheckNumber = null;

    public ?NumberCheck $numberCheck = null;

    /**
     * Per-number check results, keyed by the raw submitted number. Takes
     * precedence over `$numberCheck`.
     *
     * @var array<string, NumberCheck>
     */
    public array $numberChecks = [];

    /**
     * @var array<int, string>
     */
    public array $checkedNumbers = [];

    /**
     * @var array<int, array{chat_id: string, text: string}>
     */
    public array $sentTexts = [];

    public string $sentProviderId = 'SENT-1';

    public ?string $lastQrSession = null;

    /**
     * @var array<int, string>
     */
    public array $calls = [];

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function health(): bool
    {
        $this->calls[] = 'health';

        return $this->healthy;
    }

    public function createSession(string $name, array $webhooks = []): SessionState
    {
        $this->calls[] = 'create';
        $this->guardAction();

        return $this->session = new SessionState($name, 'STARTING');
    }

    public function session(string $name): ?SessionState
    {
        $this->calls[] = 'session';

        return $this->session;
    }

    public function startSession(string $name): void
    {
        $this->calls[] = 'start';
        $this->guardAction();
    }

    public function stopSession(string $name): void
    {
        $this->calls[] = 'stop';
        $this->guardAction();
    }

    public function restartSession(string $name): void
    {
        $this->calls[] = 'restart';
        $this->guardAction();
    }

    public function logoutSession(string $name): void
    {
        $this->calls[] = 'logout';
        $this->guardAction();
    }

    public function qr(string $name): ?string
    {
        $this->calls[] = 'qr';
        $this->lastQrSession = $name;

        if ($this->throwOnQr) {
            throw $this->throwOnQr;
        }

        return $this->qr;
    }

    public function resolvePhoneNumber(string $chatId): ?string
    {
        $this->calls[] = 'resolve_phone';

        if ($this->throwOnResolve) {
            throw $this->throwOnResolve;
        }

        return $this->resolvedPhone;
    }

    public function checkNumber(string $phone): NumberCheck
    {
        $this->calls[] = 'check_number';
        $this->checkedNumbers[] = $phone;

        if ($this->throwOnCheckNumber) {
            throw $this->throwOnCheckNumber;
        }

        if (isset($this->numberChecks[$phone])) {
            return $this->numberChecks[$phone];
        }

        return $this->numberCheck ?? NumberCheck::notExists();
    }

    public function sendText(string $chatId, string $text, array $options = []): SentMessage
    {
        $this->calls[] = 'send_text';
        $this->sentTexts[] = ['chat_id' => $chatId, 'text' => $text];
        $this->guardAction();

        return new SentMessage($this->sentProviderId);
    }

    public function sendMedia(string $chatId, array $file, ?string $caption = null): SentMessage
    {
        $this->calls[] = 'send_media';
        $this->guardAction();

        return new SentMessage($this->sentProviderId);
    }

    private function guardAction(): void
    {
        if ($this->throwOnAction) {
            throw $this->throwOnAction;
        }
    }
}
