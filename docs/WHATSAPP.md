# WhatsApp integration

## Ownership and configuration

Laravel owns business conversations/messages, consent, templates, campaigns and dispatch outcomes. WAHA owns the paired device session, QR/auth material and provider files. Application code accesses WAHA through [`WhatsAppGateway`](../app/Contracts/WhatsAppGateway.php), not by reading its volume.

[`AppServiceProvider`](../app/Providers/AppServiceProvider.php) binds [`WahaGateway`](../app/Services/WhatsApp/WahaGateway.php) when `WHATSAPP_DRIVER=waha`. The legacy `WHATSAPP_PROVIDER` is used if the driver is unset. Unsupported drivers resolve to [`UnavailableGateway`](../app/Services/WhatsApp/UnavailableGateway.php).

Keep the provider disabled for ordinary review:

```dotenv
WHATSAPP_ENABLED=false
WHATSAPP_DRIVER=waha
WHATSAPP_BASE_URL=http://127.0.0.1:3000
WHATSAPP_API_KEY=
WHATSAPP_SESSION=default
WHATSAPP_WEBHOOK_PATH=webhooks/whatsapp
WHATSAPP_WEBHOOK_SECRET=
WHATSAPP_WEBHOOK_TOLERANCE=300
WHATSAPP_ORDER_LOCALE=ar
WHATSAPP_TIMEOUT=10
WHATSAPP_CONNECT_TIMEOUT=3
WHATSAPP_VERIFY_SSL=true
```

Laravel sends its API key in `X-Api-Key`. Configure a separate strong shared webhook secret. Store actual values privately; examples provide no usable credentials.

The `wa.me` price-request link is a browser contact action using General Settings, independent of gateway enablement. Clear real contact settings in a publicly shared demo even when the gateway is disabled.

## Provider service

[`deploy/waha/docker-compose.yml`](../deploy/waha/docker-compose.yml) provides a local service pinned by default to `noweb-2026.9.1`. It binds port 3000 to loopback and keeps session state in a named volume. Supply `WAHA_API_KEY`, `WAHA_DASHBOARD_PASSWORD` and `WHATSAPP_SWAGGER_PASSWORD` privately before starting it:

```bash
docker compose -f deploy/waha/docker-compose.yml up -d
```

Docker Compose needs its WAHA environment separately from Laravel's keys. Laravel uses the plain API key; WAHA can store its `sha512:<hash>` form. Never mount production provider sessions into development/demo.

[`docker-compose.production.yml`](../deploy/waha/docker-compose.production.yml) is a separate hardened example with a pinned image digest, disabled dashboard/Swagger, rotated logs and persistent session/media volumes. It still requires host review and private credentials. Neither example provisions a public application URL, backup destination or monitoring.

## Pairing and callbacks

The authenticated Account page supports status refresh and applicable create/start/restart/stop/logout actions for the configured session. QR is served through an authenticated Laravel route. Logout requires confirmation because it unlinks the provider device session.

Set `APP_URL` to the reachable application URL before creating the session. Session creation uses the configured callback path and HMAC key. Across hosts, provide a trusted private/TLS connection for the provider API; loopback only works when services share a host/network boundary. Do not publish the WAHA API/dashboard to the internet.

Inbound events go to `POST /webhooks/whatsapp` by default. [`routes/whatsapp.php`](../routes/whatsapp.php) registers it outside session/CSRF web middleware with signature verification and rate limiting.

[`VerifyWhatsAppWebhookSignature`](../app/Http/Middleware/VerifyWhatsAppWebhookSignature.php) requires:

- A configured WAHA driver and non-empty shared secret.
- `X-Webhook-Hmac`: SHA-512 HMAC of the raw body.
- `X-Webhook-Hmac-Algorithm`, if present, set to `sha512`.
- Numeric millisecond `X-Webhook-Timestamp` and signed body `timestamp`, both inside the configured tolerance.

Missing configuration returns 503. Invalid signatures/algorithms or missing/stale timestamps return 401. [`WhatsAppWebhookController`](../app/Http/Controllers/WhatsAppWebhookController.php) deduplicates events and forwards supported messages/acknowledgements to inbox handling.

## Workers and scheduler

Automatic notices and campaigns persist message snapshots as dispatch records. [`DispatchQueue`](../app/Support/WhatsApp/Outbound/DispatchQueue.php) publishes [`SendWhatsAppDispatch`](../app/Jobs/SendWhatsAppDispatch.php) after commit to the **database** connection. Changing the default queue connection to `sync` does not change this explicit connection.

Use one priority worker for the named queues:

```bash
php artisan queue:work database --queue=whatsapp-orders,whatsapp-campaigns --sleep=3 --tries=3 --timeout=30 --backoff=30
```

For intentional local provider tests, run the scheduler in another terminal:

```bash
php artisan schedule:work
```

[`routes/console.php`](../routes/console.php) schedules `whatsapp:dispatch-campaigns` every 30 seconds with overlap prevention. It recovers pending order dispatches and enqueues due campaign recipients rather than sending directly. A global 30-second campaign cadence prevents multiple campaigns multiplying the send rate.

Use a managed worker and scheduler when hosted; [Supervisor](../deploy/supervisor.conf.example) and [cron](../deploy/scheduler.cron.example) examples are provided. The database retry window defaults to 90 seconds; keep worker/HTTP timeouts below it. Restart workers after a code release and interrupt old sub-minute scheduler processes when switching releases.

## Notification rules

[`WhatsAppOrderTemplatesSeeder`](../database/seeders/WhatsAppOrderTemplatesSeeder.php) supplies keyed built-in templates. Automatic notifications require an active template, usable recipient, enabled provider and corresponding General Settings switch.

Customer/manager switches are checked before queueing and before execution. Disabled pending dispatches become skipped and do not replay when re-enabled. In-flight messages cannot be recalled. The switches do not disable manual replies or campaigns.

Persisted dedupe keys identify automatic order events; partial-delivery notices include a delivered-quantity fingerprint. Dispatch snapshots retain the accepted body/recipient/media during recovery. Outbound failure handling is separate from successful checkout persistence.

## Consent and campaigns

Customers have `unknown`, `subscribed` or `unsubscribed` marketing state. Eligibility requires subscribed state and a usable phone. Template/customer/product eligibility is checked again at dispatch and execution. Supported incoming opt-out messages can unsubscribe a matched customer.

Campaigns provide draft preparation, recipient preview and explicit start/pause/resume/cancel actions, with per-recipient outcomes. Transactional order updates are distinct from promotional consent and have their own switches.

## Outcomes and recovery

Provider acceptance, delivery and read are different observations. Acknowledgements update stored messages only when identity can be correlated safely. LID/compound IDs can make correlation uncertain; `sent` is not delivered/read proof.

Execution distinguishes known failure from an ambiguous attempted send. Interrupted processing can become `unknown` and is not blindly resent. The dispatch view provides supported recovery actions, with retries restricted to eligible known pre-send failures. Manual inbox sends are synchronous and have separate uncertain-outcome handling.

Recovery must preserve provider session state and dispatch receipts. Restoring an older database can revive pending jobs, so reconcile outcomes before enabling workers/campaigns. Generic queue replay is not a guarantee against duplicate messaging.

## Verification

Run default tests with external sending disabled:

```bash
php artisan test --filter=WhatsApp
```

The suite checks signature/timestamp rejection, idempotency, template privacy, matching/opt-out, dispatch races, automatic switches, campaigns and mocked provider requests. Real account/session checks are separate and use isolated state and approved recipients.
