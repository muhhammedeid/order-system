<?php

namespace Tests\Fakes;

use App\Contracts\WhatsAppGateway;
use App\Support\WhatsApp\SentMessage;
use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\WhatsAppException;

class FakeWhatsAppGateway implements WhatsAppGateway
{
    public bool $enabled = true;

    public bool $healthy = true;

    public ?SessionState $session = null;

    public ?string $qr = null;

    public ?WhatsAppException $throwOnAction = null;

    public ?WhatsAppException $throwOnQr = null;

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

    public function sendText(string $chatId, string $text, array $options = []): SentMessage
    {
        return new SentMessage('fake');
    }

    public function sendMedia(string $chatId, array $file, ?string $caption = null): SentMessage
    {
        return new SentMessage('fake');
    }

    private function guardAction(): void
    {
        if ($this->throwOnAction) {
            throw $this->throwOnAction;
        }
    }
}
