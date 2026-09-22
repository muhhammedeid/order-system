<?php

namespace App\Http\Controllers;

use App\Support\WhatsApp\Inbox\InboxProcessor;
use App\Support\WhatsApp\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Inbound WAHA webhook endpoint.
 *
 * The HMAC signature, timestamp freshness and envelope replay protection are
 * enforced by VerifyWhatsAppWebhookSignature. Accepted events are persisted
 * by the Inbox processor. If processing fails, the dedupe reservation is
 * released so the provider's retry can reprocess the event; the database
 * uniqueness constraints remain the final guard against duplicates.
 */
class WhatsAppWebhookController extends Controller
{
    private const DEDUPE_TTL_HOURS = 24;

    public function __invoke(Request $request, InboxProcessor $processor): JsonResponse
    {
        $event = WebhookEvent::fromRequest(
            body: $request->json()->all(),
            requestId: $request->header('X-Webhook-Request-Id'),
            headerTimestamp: is_numeric($request->header('X-Webhook-Timestamp'))
                ? (int) $request->header('X-Webhook-Timestamp')
                : null,
        );

        $dedupeKey = $this->dedupeKey($event);

        if (! $this->firstDelivery($dedupeKey)) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            $processor->handle($event);
        } catch (Throwable $exception) {
            Cache::forget($dedupeKey);

            // Sanitized diagnostics only: no payload, no body, no credentials.
            Log::error('WhatsApp webhook processing failed', [
                'event' => $event->event,
                'session' => $event->session,
                'exception' => $exception::class,
            ]);

            return response()->json(['status' => 'failed'], 500);
        }

        // Deliberately minimal, non-PII logging: no message bodies, no
        // credentials, no session auth material.
        Log::info('WhatsApp webhook received', [
            'event' => $event->event,
            'session' => $event->session,
            'message_id' => $event->messageId(),
            'ack' => $event->ackName(),
        ]);

        return response()->json(['status' => 'accepted'], 202);
    }

    private function firstDelivery(string $dedupeKey): bool
    {
        return Cache::add($dedupeKey, true, now()->addHours(self::DEDUPE_TTL_HOURS));
    }

    private function dedupeKey(WebhookEvent $event): string
    {
        return 'whatsapp:webhook:'.$event->idempotencyKey();
    }
}
