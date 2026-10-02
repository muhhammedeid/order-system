<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppGateway;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Serves the pairing QR for the configured WAHA session to authenticated
 * admin users only.
 *
 * Security: fetched on demand, never persisted, never logged, never cached,
 * and restricted to the session from application configuration. The browser
 * never contacts WAHA directly.
 */
class WhatsAppQrController extends Controller
{
    public function __invoke(WhatsAppGateway $gateway): Response
    {
        if (! $gateway->enabled()) {
            abort(404);
        }

        $session = (string) config('whatsapp.session', 'default');

        try {
            $data = $gateway->qr($session);
        } catch (WhatsAppException $exception) {
            Log::warning('WhatsApp QR fetch failed', [
                'session' => $session,
                'reason' => $exception->getMessage(),
            ]);

            abort(404);
        }

        $binary = is_string($data) && $data !== ''
            ? base64_decode($data, true)
            : false;

        if ($binary === false) {
            abort(404);
        }

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
