<?php

namespace App\Contracts;

use App\Support\WhatsApp\NumberCheck;
use App\Support\WhatsApp\SentMessage;
use App\Support\WhatsApp\SessionState;

/**
 * The only boundary between Laravel business code and the WhatsApp
 * provider. It is intentionally small: it covers the P08-W01 foundation
 * (health, sessions, QR, outbound text/media) and nothing else. A second
 * implementation can be added later without touching callers.
 *
 * Provider session/auth/internal state stays owned by the provider.
 * Business data stays owned by Laravel.
 */
interface WhatsAppGateway
{
    public function enabled(): bool;

    public function health(): bool;

    /**
     * @param  array<int, array{url: string, events: array<int, string>, hmac?: array{key: string}}>  $webhooks
     *                                                                                                           When `hmac` is omitted the configured webhook secret is applied.
     */
    public function createSession(string $name, array $webhooks = []): SessionState;

    public function session(string $name): ?SessionState;

    public function startSession(string $name): void;

    public function stopSession(string $name): void;

    public function restartSession(string $name): void;

    public function logoutSession(string $name): void;

    /**
     * Returns the latest QR as base64 image data, or null when the
     * provider has no QR to offer (e.g. the session is already WORKING).
     */
    public function qr(string $name): ?string;

    /**
     * Best-effort verified phone resolution for a chat id. `@c.us` ids are
     * read directly; `@lid` ids are resolved through the provider when the
     * lookup is available. Returns null when no verified number exists —
     * never guesses.
     */
    public function resolvePhoneNumber(string $chatId): ?string;

    /**
     * Verifies whether a phone number is a WhatsApp account and returns the
     * provider chat id when it is.
     *
     * A successful "number does not exist" answer returns notExists(). A
     * transport, authorization or unexpected-response failure throws
     * WhatsAppException and must never be treated as an invalid number.
     *
     * @throws WhatsAppException
     */
    public function checkNumber(string $phone): NumberCheck;

    /**
     * @param  array<string, mixed>  $options  e.g. ['reply_to' => '...', 'linkPreview' => false]
     */
    public function sendText(string $chatId, string $text, array $options = []): SentMessage;

    /**
     * @param  array{mimetype: string, url?: string, data?: string, filename?: string}  $file
     */
    public function sendMedia(string $chatId, array $file, ?string $caption = null): SentMessage;
}
