<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\PhoneNumber;
use App\Support\WhatsApp\SentMessage;
use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * WAHA implementation of the WhatsAppGateway boundary.
 *
 * Laravel never reads or writes WAHA storage. Everything goes through the
 * provider HTTP API. Credentials are sent in headers and are never logged.
 */
class WahaGateway implements WhatsAppGateway
{
    public function enabled(): bool
    {
        return (config('whatsapp.driver') ?? config('whatsapp.provider')) === 'waha'
            && (bool) config('whatsapp.enabled')
            && filled(config('whatsapp.base_url'))
            && filled(config('whatsapp.api_key'));
    }

    public function health(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return $this->client()
                ->get('/health')
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function createSession(string $name, array $webhooks = []): SessionState
    {
        // Configure the built-in identity store before the session is paired.
        // Existing paired sessions are never changed by this method.
        $payload = [
            'name' => $name,
            'config' => ['noweb' => ['store' => ['enabled' => true, 'fullSync' => false]]],
        ];

        if ($webhooks !== []) {
            // The webhook endpoint fails closed without a valid HMAC, so the
            // configured secret is applied when the caller does not override it.
            $secret = (string) config('whatsapp.webhook.secret');

            if ($secret !== '') {
                foreach ($webhooks as $index => $webhook) {
                    $webhooks[$index]['hmac'] = $webhook['hmac'] ?? ['key' => $secret];
                }
            }

            $payload['config']['webhooks'] = $webhooks;
        }

        $data = $this->json('create session', 'post', '/api/sessions', $payload);

        return SessionState::fromArray($name, $data);
    }

    public function session(string $name): ?SessionState
    {
        try {
            $response = $this->client()->get('/api/sessions/'.rawurlencode($name));
        } catch (ConnectionException) {
            throw WhatsAppException::unreachable('session');
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw WhatsAppException::requestFailed('session', $response->status());
        }

        return SessionState::fromArray($name, $response->json() ?? []);
    }

    public function startSession(string $name): void
    {
        $this->json('start session', 'post', '/api/sessions/'.rawurlencode($name).'/start', []);
    }

    public function stopSession(string $name): void
    {
        $this->json('stop session', 'post', '/api/sessions/'.rawurlencode($name).'/stop', []);
    }

    public function restartSession(string $name): void
    {
        $this->json('restart session', 'post', '/api/sessions/'.rawurlencode($name).'/restart', []);
    }

    public function logoutSession(string $name): void
    {
        $this->json('logout session', 'post', '/api/sessions/'.rawurlencode($name).'/logout', []);
    }

    public function qr(string $name): ?string
    {
        // WAHA 2026.9.1 exposes the QR as GET /api/{session}/auth/qr and
        // returns {"mimetype": "...", "data": "<base64>"}.
        try {
            $response = $this->client()->get('/api/'.rawurlencode($name).'/auth/qr');
        } catch (ConnectionException) {
            throw WhatsAppException::unreachable('qr');
        }

        // A missing session has no QR; every other failure is a real error.
        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw WhatsAppException::requestFailed('qr', $response->status());
        }

        $data = $response->json('data');

        return is_string($data) && $data !== '' ? $data : null;
    }

    public function resolvePhoneNumber(string $chatId): ?string
    {
        $phone = PhoneNumber::fromChatId($chatId);

        if ($phone !== null) {
            return $phone;
        }

        if (! str_ends_with($chatId, '@lid') || ! $this->enabled()) {
            return null;
        }

        try {
            $response = $this->client()
                ->get('/api/'.rawurlencode($this->sessionName()).'/lids/'.rawurlencode($chatId));
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            // The lookup is best-effort: a disabled NOWEB store or a missing
            // mapping (400/404) simply leaves the conversation unlinked.
            return null;
        }

        $pn = $response->json('pn');

        return is_string($pn) ? PhoneNumber::fromChatId($pn) : null;
    }

    public function checkNumber(string $phone): NumberCheck
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return NumberCheck::notExists();
        }

        try {
            $response = $this->client()->get('/api/contacts/check-exists', [
                'phone' => $digits,
                'session' => $this->sessionName(),
            ]);
        } catch (ConnectionException) {
            throw WhatsAppException::unreachable('check number');
        }

        if (! $response->successful()) {
            throw WhatsAppException::requestFailed('check number', $response->status());
        }

        $exists = $response->json('numberExists');

        if (! is_bool($exists)) {
            throw WhatsAppException::unexpectedResponse('check number');
        }

        if (! $exists) {
            return NumberCheck::notExists();
        }

        $chatId = $response->json('chatId');

        if (! is_string($chatId) || $chatId === '') {
            throw WhatsAppException::unexpectedResponse('check number');
        }

        $pn = $response->json('pn');
        $resolvedPhone = is_string($pn)
            ? PhoneNumber::fromChatId($pn)
            : PhoneNumber::fromChatId($chatId);

        return NumberCheck::exists($chatId, $resolvedPhone);
    }

    public function sendText(string $chatId, string $text, array $options = []): SentMessage
    {
        $payload = array_merge($options, [
            'session' => $this->sessionName(),
            'chatId' => $chatId,
            'text' => $text,
        ]);

        $data = $this->json('send text', 'post', '/api/sendText', $payload);

        return WahaSentMessage::fromArray($data);
    }

    public function sendMedia(string $chatId, array $file, ?string $caption = null): SentMessage
    {
        $endpoint = str_starts_with((string) ($file['mimetype'] ?? ''), 'image/')
            ? '/api/sendImage'
            : '/api/sendFile';

        $payload = [
            'session' => $this->sessionName(),
            'chatId' => $chatId,
            'file' => $file,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $data = $this->json('send media', 'post', $endpoint, $payload);

        return WahaSentMessage::fromArray($data);
    }

    private function sessionName(): string
    {
        return (string) config('whatsapp.session', 'default');
    }

    private function client(): PendingRequest
    {
        if (! $this->enabled()) {
            throw WhatsAppException::notConfigured();
        }

        return Http::baseUrl((string) config('whatsapp.base_url'))
            ->withHeaders(['X-Api-Key' => (string) config('whatsapp.api_key')])
            ->acceptJson()
            ->asJson()
            ->withoutRedirecting()
            ->timeout(max(1, min(10, (int) config('whatsapp.timeout', 10))))
            ->connectTimeout(max(1, min(3, (int) config('whatsapp.connect_timeout', 3))))
            ->withOptions(['verify' => (bool) config('whatsapp.verify_ssl', true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $action, string $method, string $uri, array $payload): array
    {
        try {
            $response = $this->client()->{$method}($uri, $payload);
        } catch (ConnectionException) {
            // WAHA can hang without an HTTP response (for example when the
            // session is not WORKING); surface a sanitized failure instead
            // of leaking the transport exception to callers.
            throw WhatsAppException::unreachable($action);
        }

        if (! $response->successful()) {
            throw WhatsAppException::requestFailed($action, $response->status());
        }

        return $response->json() ?? [];
    }
}
