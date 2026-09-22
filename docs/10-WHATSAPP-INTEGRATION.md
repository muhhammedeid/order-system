# Wholesale Order System — WhatsApp Integration (Phase 08)

This document owns the WhatsApp module boundary. P08-W01 is a provider spike and
foundation only; inbox, conversations, customer linking, order communication, templates,
marketing consent, campaigns, scheduling, throttling and campaign safety are later
packages.

## Provider Decision

- **Approved provider: WAHA CORE.** OpenWA is rejected for the current implementation.
- Only one provider is supported. Business code depends on
  `App\Contracts\WhatsAppGateway`, not on WAHA directly.
- No provider registry, provider factory hierarchy or speculative multi-provider
  architecture exists. A future replacement is a one-class change plus a new binding.
- Recorded during the P08-W01 spike: **WAHA 2026.9.1, engine NOWEB, tier CORE**.

## Architecture

```text
Laravel / Filament / Artisan
        |  (business data stays in MySQL)
        v
App\Contracts\WhatsAppGateway
        |
App\Services\WhatsApp\WahaGateway   (Laravel Http, X-Api-Key, timeout)
        |  private network only
        v
WAHA container (owns session/auth/QR/media state)

WAHA event -> POST /webhooks/whatsapp (HMAC-SHA512) -> VerifyWhatsAppWebhookSignature
           -> WhatsAppWebhookController -> duplicate/replay protection (database cache)
```

Rules:

- Laravel never reads or writes WAHA storage or session files.
- Provider session/auth/internal state is owned by WAHA; business data is owned by Laravel.
- WAHA is not exposed to the public internet. Only WAHA's egress to the Laravel webhook URL
  crosses the boundary.

## Files (P08-W01)

| File | Purpose |
|---|---|
| `config/whatsapp.php` | Provider, connection, webhook configuration |
| `app/Contracts/WhatsAppGateway.php` | The only business/provider boundary |
| `app/Services/WhatsApp/WahaGateway.php` | WAHA HTTP implementation |
| `app/Support/WhatsApp/SessionState.php` | Normalized session state |
| `app/Support/WhatsApp/SentMessage.php` | Normalized outbound result |
| `app/Support/WhatsApp/WebhookEvent.php` | Normalized inbound event + idempotency key |
| `app/Support/WhatsApp/WhatsAppException.php` | Sanitized provider failure |
| `app/Http/Middleware/VerifyWhatsAppWebhookSignature.php` | HMAC-SHA512 verification |
| `app/Http/Controllers/WhatsAppWebhookController.php` | Stateless webhook endpoint |
| `routes/whatsapp.php` | Webhook route (no session, no CSRF) |
| `deploy/waha/docker-compose.yml` | Local WAHA service with persistent volume |

## Configuration

Environment variables (never commit real values):

| Variable | Purpose |
|---|---|
| `WHATSAPP_ENABLED` | Kill switch; outbound calls fail closed when false |
| `WHATSAPP_PROVIDER` | `waha` (single approved provider) |
| `WHATSAPP_BASE_URL` | WAHA base URL; private network in production |
| `WHATSAPP_API_KEY` | Sent as `X-Api-Key`; store hashed on the WAHA side |
| `WHATSAPP_SESSION` | Default session name |
| `WHATSAPP_TIMEOUT` | HTTP timeout in seconds |
| `WHATSAPP_VERIFY_SSL` | TLS verification for cross-host deployments |
| `WHATSAPP_WEBHOOK_PATH` | Inbound webhook path |
| `WHATSAPP_WEBHOOK_SECRET` | HMAC key; endpoint fails closed when empty |
| `WHATSAPP_WEBHOOK_TOLERANCE` | Replay window in seconds for `X-Webhook-Timestamp` |

WAHA-side secrets support the recommended hashed form `WAHA_API_KEY=sha512:{hash}`;
Laravel always sends the plain key in the `X-Api-Key` header.

## Webhook Security

- HMAC-SHA512 over the **raw request body**, compared with `hash_equals`.
- Only `sha512` is accepted; any other declared algorithm is rejected.
- `X-Webhook-Timestamp` is required (missing or malformed is rejected) and must be inside
  the replay window (default 300 s); WAHA retries stay well inside it.
- Duplicate protection uses the WAHA envelope `id` (`evt_...`, part of the signed body),
  falling back to a SHA-256 hash of the raw body, then to `X-Webhook-Request-Id`. Keys are
  stored for 24 h.
- Route is stateless (no session, no CSRF), throttled, and fails closed when unconfigured.
- Logging is minimal and non-PII: event name, session, message id, ack name. No message
  bodies, credentials, or QR/auth material.
- `createSession()` applies the configured webhook secret as the WAHA `hmac.key` unless the
  caller supplies an explicit override, so inbound webhooks are signed from the start.
- Note for later packages: dedupe is recorded before event handling. When real handlers are
  added (inbox/campaign work), a handler that fails after the dedupe key is written will
  need an explicit retry/replay path.

## Local Spike / Development

From `deploy/waha`:

```bash
export WAHA_API_KEY=...            # or sha512:{hash}
export WAHA_DASHBOARD_PASSWORD=...
export WHATSAPP_SWAGGER_PASSWORD=...
docker compose up -d
```

The container binds to `127.0.0.1:3000` and persists sessions in the `waha_sessions`
volume. The image is pinned to the spike version (`2026.9.1`) by default and can be
overridden with `WAHA_IMAGE_TAG`. Point `WHATSAPP_BASE_URL` at it and set
`WHATSAPP_WEBHOOK_SECRET` to the HMAC key configured on the WAHA session webhook.

## Staging / Production Hosting Requirement

WAHA requires an **always-on container with a persistent volume**. The current staging
environment (`render.yaml`: single free Render Docker web service) is **not suitable**
because:

- the free plan has no persistent disk, so a paired session would be lost on every deploy
  or restart, forcing re-pairing;
- free web services spin down when idle, which drops the WhatsApp connection and webhooks;
- the free plan has no private networking, so the provider API and dashboard would need a
  public endpoint or a tunnel.

The hosting decision is **deferred to after the spike passes**; P08-W01 does not purchase,
provision or migrate infrastructure. Options to evaluate later: a paid Render service with
a disk and private networking, or a separate small VPS with a private link to the Laravel
host. Across separate hosts, HTTPS and a private network/VPN are required, and WAHA must
never be publicly exposed.

## Queue Position

No queues, jobs, workers or Redis are introduced in P08-W01. Database Queue is evaluated
in the later campaign package. Note that staging currently runs `QUEUE_CONNECTION=sync`
with no worker, so campaign work will need a worker process.

## P08-W01 Spike Results

Environment: WAHA `2026.9.1`, engine `NOWEB`, tier `CORE`, Docker on Windows.
P08-W01A completed live pairing and end-to-end verification with a real WhatsApp account.

| # | Scenario | Result |
|---|---|---|
| 1 | Create session | PASS (`POST /api/sessions`) |
| 2 | Obtain QR | PASS (`GET /api/{session}/auth/qr`, base64 PNG) |
| 3 | Pair a real device | PASS |
| 4 | Reach `WORKING` | PASS |
| 5 | Send text | PASS through `WahaGateway::sendText` (real message delivered) |
| 6 | Send media | PASS through `WahaGateway::sendMedia` (real image delivered) |
| 7 | Receive inbound message | PASS (real inbound `message` webhook accepted by Laravel) |
| 8 | Observe ack/status | PASS — `SERVER` then `DEVICE` observed for outbound messages |
| 9 | Restart/recreate WAHA | PASS (container restart and full `docker run` recreate) |
| 10 | Session persists without re-pairing | PASS — `WORKING` after restart and after container recreate |
| 11 | Disconnect/reconnect | PASS — `logout` cleared `me` and moved to `SCAN_QR_CODE`; re-pair restored `WORKING`; config preserved |
| 12 | Replay webhook duplicate protection | PASS with a real captured event (202 accepted, then 200 duplicate; tampered body 401) |
| 13 | Health endpoint | PASS (`GET /health` 200 with key; 401 without) |
| 14 | Invalid API credentials rejected | PASS (401) |

Ack transitions observed for outbound messages: `PENDING` (send response `status`),
`SERVER` (`ack=1`), `DEVICE` (`ack=2`). `READ` (`ack=3`) was **not** emitted by this
engine/account even after the recipient opened the messages, so later packages must not
assume `READ` is always available.

Observed payload shapes (values redacted; envelope fields are common to all events):

```jsonc
// message (inbound)
{
  "id": "evt_<ULID>",
  "timestamp": 1790000000000,
  "event": "message",
  "session": "live",
  "me": { "id": "<account>@c.us", "pushName": "..." },
  "payload": {
    "id": "false_<chat>@lid_<message-id>",
    "timestamp": 1790000000,
    "from": "<sender>@lid",
    "fromMe": false,
    "source": "app",
    "body": "<message text>",
    "hasMedia": false,
    "media": null,
    "ack": 2,
    "ackName": "DEVICE",
    "location": null,
    "vCards": [],
    "replyTo": {
      "id": "<message-id>", "participant": "<sender>@lid", "body": "...",
      "hasMedia": false, "media": null, "_data": {}
    },
    "_data": {
      "key": {}, "messageTimestamp": "...", "pushName": "...",
      "broadcast": false, "message": {}, "status": "..."
    }
  },
  "engine": "NOWEB",
  "environment": { "version": "2026.9.1", "engine": "NOWEB", "tier": "CORE" }
}
```

```jsonc
// message.ack
{
  "id": "evt_<ULID>",
  "timestamp": 1790000000000,
  "event": "message.ack",
  "session": "live",
  "me": { "id": "<account>@c.us", "pushName": "..." },
  "payload": {
    "id": "true_<chat>@lid_<message-id>",
    "from": "<chat>@lid",
    "fromMe": true,
    "ack": 2,
    "ackName": "DEVICE"
  },
  "engine": "NOWEB",
  "environment": { "version": "2026.9.1", "engine": "NOWEB", "tier": "CORE" }
}
```

Notes on the observed data:

- `payload.from` and the ack `payload.id` can use the `@lid` hidden user id instead of the
  `@c.us` phone JID used when sending; later packages must correlate by the message-id
  suffix, not by the chat JID.
- The `message.ack` payload has no `participant` field on this engine (the WAHA docs
  example showed `participant: null`).
- `_data` is engine-specific and is not relied upon.

Operational notes for later P08 packages:

- A session created through the API starts in `STOPPED` and must be started explicitly
  (the WAHA docs say creation starts it by default).
- QR codes rotate about every 20 seconds; leaving one unscanned moves the session to
  `FAILED` after roughly two minutes. `restart` returns it to `SCAN_QR_CODE`.
- On logout/unlink the session config is preserved but the device must be paired again.
- The webhook dedupe stores keys in the database cache. When the database is unavailable
  the endpoint returns 500 and WAHA retries the delivery (observed), which is the expected
  fail-closed behavior; retries are deduplicated once the database is reachable.
- WAHA delivers the same event to every configured webhook, so running a capture listener
  alongside Laravel is a valid debugging setup.

## Known Limitations

- QR retrieval is `GET /api/{session}/auth/qr` in WAHA 2026.9.1 (older docs showed POST).
- `sendText`/`sendMedia` responses use `key.id` and `messageTimestamp`, not the documented
  top-level `id`/`timestamp`. `SentMessage` handles both; this was a real defect found by
  live verification and fixed with a regression test.
- WAHA send endpoints can hang without an HTTP response while the session is not `WORKING`;
  the gateway timeout converts this into a sanitized `WhatsAppException`.
- `READ` acknowledgements were not produced on this engine/account; only `SERVER` and
  `DEVICE` were observed.
- Application logs contain the event name, session name, provider message id (which embeds
  the chat JID/LID) and ack name, but never QR data, message bodies, API keys or the HMAC
  secret.
- Using an unofficial client carries account/session ban risk.
