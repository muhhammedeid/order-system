<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\SentMessage;
use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\WhatsAppException;

/** Unimplemented drivers fail closed without falling back to a different account. */
class UnavailableGateway implements WhatsAppGateway
{
    public function enabled(): bool
    {
        return false;
    }

    public function health(): bool
    {
        return false;
    }

    public function createSession(string $name, array $webhooks = []): SessionState
    {
        throw WhatsAppException::notConfigured();
    }

    public function session(string $name): ?SessionState
    {
        return null;
    }

    public function startSession(string $name): void
    {
        throw WhatsAppException::notConfigured();
    }

    public function stopSession(string $name): void
    {
        throw WhatsAppException::notConfigured();
    }

    public function restartSession(string $name): void
    {
        throw WhatsAppException::notConfigured();
    }

    public function logoutSession(string $name): void
    {
        throw WhatsAppException::notConfigured();
    }

    public function qr(string $name): ?string
    {
        return null;
    }

    public function resolvePhoneNumber(string $chatId): ?string
    {
        return null;
    }

    public function checkNumber(string $phone): NumberCheck
    {
        throw WhatsAppException::notConfigured();
    }

    public function sendText(string $chatId, string $text, array $options = []): SentMessage
    {
        throw WhatsAppException::notConfigured();
    }

    public function sendMedia(string $chatId, array $file, ?string $caption = null): SentMessage
    {
        throw WhatsAppException::notConfigured();
    }
}
