<?php

namespace App\Http\Controllers;

use App\Support\WhatsApp\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Inbound WAHA webhook endpoint for the P08-W01 foundation.
 *
 * It verifies (via middleware), records and de-duplicates events. It does
 * not implement inbox, conversation, template, campaign or reply logic;
 * those belong to later P08 packages. Handlers subscribe to the stored
 * event later without changing this endpoint's contract.
 */
class WhatsAppWebhookController extends Controller
{
    private const DEDUPE_TTL_HOURS = 24;

    public function __invoke(Request $request): JsonResponse
    {
        $event = WebhookEvent::fromRequest(
            body: $request->json()->all(),
            requestId: $request->header('X-Webhook-Request-Id'),
            headerTimestamp: is_numeric($request->header('X-Webhook-Timestamp'))
                ? (int) $request->header('X-Webhook-Timestamp')
                : null,
        );

        if (! $this->firstDelivery($event)) {
            return response()->json(['status' => 'duplicate'], 200);
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

    private function firstDelivery(WebhookEvent $event): bool
    {
        return Cache::add(
            'whatsapp:webhook:'.$event->idempotencyKey(),
            true,
            now()->addHours(self::DEDUPE_TTL_HOURS),
        );
    }
}
