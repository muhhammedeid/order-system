<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that an inbound WhatsApp webhook really comes from the provider.
 *
 * WAHA signs the raw request body with HMAC. The signature is compared in
 * constant time. When no secret is configured the endpoint fails closed.
 */
class VerifyWhatsAppWebhookSignature
{
    private const ALLOWED_ALGORITHMS = ['sha512'];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('demo.enabled')) {
            abort(503, 'WhatsApp integration is disabled in this demonstration.');
        }

        if ((config('whatsapp.driver') ?? config('whatsapp.provider', 'waha')) !== 'waha') {
            abort(503, 'Webhook provider is not available.');
        }
        $secret = (string) config('whatsapp.webhook.secret');

        if ($secret === '') {
            abort(503, 'WhatsApp webhook is not configured.');
        }

        $signature = (string) $request->header('X-Webhook-Hmac', '');
        $algorithm = strtolower((string) $request->header('X-Webhook-Hmac-Algorithm', 'sha512'));

        if ($signature === '' || ! in_array($algorithm, self::ALLOWED_ALGORITHMS, true)) {
            abort(401, 'Invalid webhook signature.');
        }

        $expected = hash_hmac($algorithm, $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        if (! $this->timestampWithinTolerance($request)) {
            abort(401, 'Stale webhook timestamp.');
        }

        return $next($request);
    }

    private function timestampWithinTolerance(Request $request): bool
    {
        $header = $request->header('X-Webhook-Timestamp');

        // WAHA always sends this header; fail closed when it is missing or
        // malformed instead of silently accepting a potentially replayed body.
        $signedTimestamp = $request->json('timestamp');
        if (! is_numeric($header) || ! is_numeric($signedTimestamp)) {
            return false;
        }

        $tolerance = (int) config('whatsapp.webhook.tolerance', 300);

        if ($tolerance <= 0) {
            return false;
        }

        // WAHA sends the header in milliseconds.
        $seconds = (int) floor(((int) $header) / 1000);
        $signedSeconds = (int) floor(((int) $signedTimestamp) / 1000);

        return abs(time() - $seconds) <= $tolerance && abs(time() - $signedSeconds) <= $tolerance;
    }
}
