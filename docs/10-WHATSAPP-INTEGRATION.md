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
- Algorithm allowlist (`sha512`, `sha256`); anything else is rejected.
- `X-Webhook-Timestamp` replay window (default 300 s); WAHA retries stay well inside it.
- Duplicate protection uses the WAHA envelope `id` (`evt_...`), falling back to
  `X-Webhook-Request-Id`, then to a SHA-256 hash of the raw body, stored for 24 h.
- Route is stateless (no session, no CSRF), throttled, and fails closed when unconfigured.
- Logging is minimal and non-PII: event name, session, message id, ack name. No message
  bodies, credentials, or QR/auth material.

## Local Spike / Development

From `deploy/waha`:

```bash
export WAHA_API_KEY=...            # or sha512:{hash}
export WAHA_DASHBOARD_PASSWORD=...
export WHATSAPP_SWAGGER_PASSWORD=...
docker compose up -d
```

The container binds to `127.0.0.1:3000` and persists sessions in the `waha_sessions`
volume. Point `WHATSAPP_BASE_URL` at it and set `WHATSAPP_WEBHOOK_SECRET` to the HMAC key
configured on the WAHA session webhook.

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

| # | Scenario | Result |
|---|---|---|
| 1 | Create session | PASS (`POST /api/sessions`) |
| 2 | Obtain QR | PASS (`GET /api/{session}/auth/qr`, base64 PNG) |
| 3 | Pair a real device | NOT RUN — requires a real WhatsApp account and operator |
| 4 | Reach `WORKING` | NOT RUN — depends on scenario 3 |
| 5 | Send text | NOT RUN — depends on scenario 3 |
| 6 | Send media | NOT RUN — depends on scenario 3 |
| 7 | Receive inbound message | NOT RUN — depends on scenario 3 |
| 8 | Observe ack/status | NOT RUN — depends on scenario 3; documented shape used |
| 9 | Restart WAHA | PASS (container restart and recreate) |
| 10 | Session persists without re-pairing | PASS for session state/config across restart and recreate; full `WORKING` persistence depends on scenario 3 |
| 11 | Disconnect/reconnect | PARTIAL — stop/start transitions verified; real unlink depends on scenario 3 |
| 12 | Replay webhook duplicate protection | PASS with a real captured payload (202 then 200 duplicate; tampered body 401) |
| 13 | Health endpoint | PASS (`GET /health` 200 with key; 401 without) |
| 14 | Invalid API credentials rejected | PASS (401) |

Observed payload shapes (live capture):

```jsonc
// session.status
{
  "id": "evt_01m342gajes9cv2h56dj6fgbzk",
  "timestamp": 1790064471511,
  "event": "session.status",
  "session": "spike",
  "me": null,
  "payload": { "name": "spike", "status": "SCAN_QR_CODE",
    "statuses": [{ "status": "STARTING", "timestamp": 1790064470606 }], "data": null },
  "engine": "NOWEB",
  "environment": { "version": "2026.9.1", "engine": "NOWEB", "tier": "CORE" }
}
```

Headers observed: `X-Webhook-Request-Id`, `X-Webhook-Timestamp` (ms),
`X-Webhook-Hmac` (sha512 hex), `X-Webhook-Hmac-Algorithm: sha512`, `User-Agent: WAHA/2026.9.1`.

`message` and `message.ack` shapes follow the documented WAHA envelope with
`payload.id`, `payload.ack`, `payload.ackName`; live capture is pending pairing
(scenarios 3–8). Parsing is defensive and covered by automated tests.

## Known Limitations

- QR retrieval is `GET /api/{session}/auth/qr` in WAHA 2026.9.1 (older docs showed POST).
- WAHA send endpoints can hang without an HTTP response while the session is not `WORKING`;
  the gateway timeout converts this into a sanitized `WhatsAppException`.
- Pairing, sending, receiving and ack scenarios need a real WhatsApp account; using an
  unofficial client carries account/session ban risk.
