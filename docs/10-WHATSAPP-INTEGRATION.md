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

## P08-W02 — WhatsApp Account & Sessions (Admin)

The Filament page **Account** (navigation group **WhatsApp**) operates the single WAHA
session defined by `config('whatsapp.session')`. It reads and writes nothing in the
database: the configured session remains the only source of truth and WAHA keeps ownership
of session/auth state. `restartSession()` was added to `WhatsAppGateway` for this package.

The page shows two separate cards: **WAHA Service** (Healthy / Unavailable / Disabled) and
**WhatsApp Account** (business state below). Provider errors are caught and shown as
sanitized notifications; the provider message is logged server-side without credentials,
QR data or payloads.

| WAHA raw | Admin state | Actions |
|---|---|---|
| `WORKING` | Connected | Refresh, Restart, Stop, Logout |
| `STARTING` | Connecting | Refresh |
| `SCAN_QR_CODE` | QR Required | Refresh QR, Stop |
| `STOPPED` | Stopped | Start, Refresh |
| `FAILED` | Attention Required | Refresh, Restart, Logout |
| `PASSKEY_REQUIRED`, `PASSKEY_CONFIRMATION_REQUIRED` | Verification Required | Refresh, Restart |
| session missing | Not Set Up | Create & Start, Refresh |
| config disabled | Disabled | Refresh |
| health unreachable | Service Unavailable | Refresh |

Any other raw status falls back to Attention Required; the raw value is preserved and shown
only as a small diagnostic line.

Operational behavior:

- All actions re-check the live provider state server-side before executing and report a
  "state changed" warning instead of acting on stale UI state.
- **Logout/Unlink** is destructive: a confirmation modal explains that the linked device is
  removed and a new QR scan is required. Logout is never triggered automatically to recover
  from connection problems.
- **Create & Start** can only create the session from application configuration; there is
  no session-name input and no multi-account UI.
- Status/health refresh happens on page load, manual refresh, and after each action. No
  general polling exists; only the QR image auto-refreshes (~20 s) while QR is required,
  matching WAHA's rotation.

QR security: the QR is served by an authenticated Laravel route
(`/admin/whatsapp/qr`, `filament.admin.whatsapp.qr`). It is fetched from WAHA on demand,
never persisted, never logged, returned as a binary PNG with `Cache-Control: no-store`, and
restricted to the configured session (no session parameter). Guests are redirected to the
admin login. The browser never contacts WAHA directly.

Authorization uses the existing single-Admin Filament model. Granular WhatsApp permissions
(per-user or per-role) are not part of P08-W02 and would need a later approved scope if the
Admin user base expands.

P08-W02 live QA (paired local WAHA `2026.9.1`, engine `NOWEB`): Connected state with
identity, browser-driven Logout confirmation, QR Required with automatic rotation,
re-pair back to Connected, Restart, Stop, Start, WAHA container unavailable → Service
Unavailable → recovery to Connected, disabled integration → Disabled with provider calls
refused, Arabic/RTL desktop and mobile, and a log scan confirming zero QR data, API keys,
HMAC secrets, dashboard passwords or phone numbers in `laravel.log`.

## P08-W03 — WhatsApp Inbox & Conversations

Laravel now owns the business communication history for one-to-one chats. No raw provider
payload, engine `_data`, auth/session state or media file is stored.

### Schema

`whatsapp_conversations`: `id`, `customer_id` (nullable, `nullOnDelete`), `provider_chat_id`
(unique), `resolved_phone` (nullable, verified only), `last_message_at`,
`last_message_preview`, `last_message_direction`, `unread_count`, timestamps. Indexes:
`unique(provider_chat_id)`, `customer_id`, `last_message_at`.

`whatsapp_messages`: `id`, `conversation_id` (`cascadeOnDelete`), `provider_message_id`
(nullable), `direction`, `message_type`, `body` (text only), `status` (outbound only),
`provider_ack`, `provider_ack_name`, `occurred_at`, timestamps. Indexes:
`unique(conversation_id, provider_message_id)`, `(conversation_id, occurred_at)`.

A non-unique index was added on `customers.whatsapp` for inbound matching. `display_name`
is intentionally not stored: the only safe source is the WAHA contacts API, which needs the
NOWEB store, so titles fall back to the linked customer, the verified phone, or a neutral
label plus a short chat-id suffix.

### Message identity and ACK correlation

WAHA event ids are `{fromMe}_{chatId}_{messageId}` while `sendText`/`sendMedia` responses
return the bare `{messageId}` token. Provider identity therefore includes chat context:
uniqueness is `(conversation_id, provider_message_id)` — **not** the token alone — and ACK
correlation resolves the conversation from the ack's chat id first, then the message token
inside that conversation. Verified live: the ack for a panel-sent message matched its stored
row, and the same token is allowed in different conversations.

### Identity and customer matching

`@c.us` ids yield the phone directly. `@lid` ids are resolved through
`GET /api/{session}/lids/{lid}`; the lookup is best-effort and returns null when the store
is disabled, the mapping is unknown, or the provider is unreachable. Unresolved chats are
created unlinked and remain fully usable, including replies to the `@lid` chat id.

Matching is deterministic and exact: verified phone → candidate spellings (international,
`+`, Egyptian national, `00` prefix) → exact match on `customers.phone` or
`customers.whatsapp` → link only when exactly one distinct customer matches. Ambiguous or
unknown numbers stay unlinked; no customer is ever auto-created. Admins can link, change or
unlink a customer from the conversation header, which writes only `customer_id`.

### Event handling

- `message` with `fromMe=false` → inbound (stored, unread +1).
- `message.any` with `fromMe=true, source=app` → phone-sent outbound history (stored once).
- `message.any` with `fromMe=true, source=api` → ignored (the panel flow already stored it).
- `message.any` with `fromMe=false` → ignored (already handled by `message`).
- `message.ack` with `fromMe=true` → monotonic status update on the matching outbound row.
- Groups, broadcasts, status and channels are ignored in application processing.

Idempotency: the envelope replay cache runs first; if processing throws, the reservation is
released so the provider retry can reprocess (verified live during a database outage), and
the unique `(conversation_id, provider_message_id)` index is the final guard.

### Outbound lifecycle and status

Replies are stored as `pending`, sent through the gateway, then marked `sent` with the
provider id; a provider failure leaves a `failed` row and a sanitized notification. No
queue. Provider acks map monotonically: `PENDING → pending`, `SERVER → sent`,
`DEVICE → delivered`, `READ → read`, `ERROR → failed`; unknown names are preserved without
changing status, and `failed` is terminal. Inbound rows have no delivery status, and no
behavior depends on READ.

### Inbox UX

`WhatsApp → Inbox` is a read-only resource: identity, linked-customer badge, last-message
preview with direction, last activity, unread badge, newest first, search by customer
name/phone/code, verified phone or chat id. The navigation badge counts conversations with
unread messages. The conversation view shows identity, linked customer, verified phone, a
small provider-chat diagnostic, an escaped chronological timeline with outbound status and
non-text placeholders, and a text composer (Link / Change / Unlink customer in the header).
Opening a conversation resets its unread counter.

### P08-W03 live QA (WAHA 2026.9.1, NOWEB, paired account)

- Inbound `@lid` message → conversation created, `resolved_phone` = the verified number,
  linked to the matching customer, unread incremented and reset on open.
- Panel reply to the `@lid` chat id → delivered with `SERVER` → `DEVICE` acks updating the
  stored row.
- Phone-sent message → persisted exactly once via `message.any source=app`; the
  `source=api` event for a panel send was ignored (no duplicate).
- Duplicate/replay and failure-release behavior re-verified.
- Log scan: no message bodies, QR data, API keys, HMAC secrets or phone numbers.

### Operational notes

- **NOWEB store prerequisite:** `@lid`→phone resolution and contact names require
  `config.noweb.store.enabled=true` on the WAHA session and the contact to be present in
  the paired account's address book. Enabling it restarts the session but does not require
  re-pairing. The Inbox works fully without it (unlinked conversations).
- ACK webhooks are not guaranteed for every message: WAHA's store showed a panel-sent
  message at `DEVICE` while no `message.ack` webhook was pushed, so such a row correctly
  remains `sent`. `READ` webhooks are also not reliable.
- The webhook endpoint fails closed (503) when `WHATSAPP_WEBHOOK_SECRET` is missing; a local
  server started without the `WHATSAPP_*` environment variables therefore causes WAHA to
  retry and eventually drop events. Always start the local/admin server with the module
  configuration present.

## P08-W04 — Order Communication Integration

Orders communicate through the existing conversation and message history; no second
messaging system exists.

### Relationship model

`whatsapp_messages.order_id` is a nullable FK to `orders` with `nullOnDelete`, indexed as
`(order_id, occurred_at)`. The relation lives on the message because only a message can be
"about" an order: one conversation spans many orders and the conversation stays
customer/chat scoped. Existing history keeps `order_id = null`, general and phone-sent
messages stay unlinked, and messages survive an order removal. No `conversation_id` was
added to `orders` and no pivot table exists.

### Number verification model

`WhatsAppGateway::checkNumber()` returns a typed `NumberCheck`:

- `NumberCheck::exists($chatId, $phone)` — WAHA `check-exists` confirmed the account;
- `NumberCheck::notExists()` — the provider successfully answered `numberExists = false`;
- transport, HTTP, authorization or unexpected-response failures **throw**
  `WhatsAppException` and are never treated as an invalid number.

Non-numeric input is a local "does not exist" without a provider call. The panel shows
**رقم هذا العميل غير مسجّل على واتساب** for a confirmed missing number and
**خدمة واتساب غير متاحة حاليًا — حاول لاحقًا** for a provider failure.

### First contact and conversation resolution

1. Conversations linked to the order's customer, newest activity first.
2. Exactly one → used.
3. More than one → explicit operator selection (chat id, verified phone, last activity);
   nothing is merged, and a conversation linked to another customer is never reassigned.
4. None → the candidate number is `customer.whatsapp` else `customer.phone` (neither field
   is ever modified). `check-exists` verifies it; on success the returned `chatId` is reused
   or the local conversation shell is created, then linked to the order's customer when it
   was unlinked.

The shell is created **only** during an actual send attempt after successful verification:
rendering an order panel performs no provider call and creates nothing. Missing number,
confirmed non-WhatsApp number and provider failure each produce their own operator message
and never send blindly.

### Predefined operational messages

Composed by `OrderMessageTemplates` from translations plus the order number, customer name
and approved delivery totals; a read-only preview is shown before Send and there is no
template table or editor. Only the current status has a template:

| Status | Template |
|---|---|
| `new` | Order Received |
| `confirmed` | Order Confirmed (preparation started) |
| `partially_delivered` | Partial Delivery Update (delivered / remaining) |
| `delivered` | Order Delivered |
| `cancelled` | Order Cancelled |

There is no "Production Update" template: no production lifecycle status exists, and R03's
outstanding-production aggregate is internal. `admin_notes`, `customer_notes`, prices and
production aggregates are never included (covered by tests). Status changes never send
anything automatically.

### Outbound architecture

The existing conversation-composer lifecycle moved into
`App\Support\WhatsApp\Outbound\MessageSender::send($conversation, $body, ?int $orderId)`
(pending → provider send → sent + provider id, or failed retained and rethrown). The
conversation page and the order panel both use it; no queue, repository, event bus or
workflow engine was added. Order-panel sends set `order_id`; the conversation composer and
phone-sent `message.any` messages keep `order_id = null`. ACKs keep updating the same row.

### Order panel and conversation timeline

The compact panel is embedded on both order view pages (Order Management and the read-only
Delivered Orders view) via a plain Livewire component: availability badge, customer and
verified phone, selected/existing conversation, Open Conversation, up to five order-linked
messages (general history only via the conversation link), custom composer, predefined
buttons with read-only preview, and the conversation selector when several exist.
Messages with `order_id` show a small `#ORD-…` badge in the conversation timeline linking
back to the order; unlinked messages render unchanged.

### P08-W04 live QA (WAHA 2026.9.1, NOWEB, paired account)

- Existing single conversation used; multiple conversations blocked sending until one was
  selected in the browser.
- First contact verified a real number and created the conversation only on send; a
  non-WhatsApp number was refused; stopping WAHA produced the service-unavailable message
  instead of an invalid-number message.
- Custom and predefined order messages were delivered with `order_id` set and stayed `sent`
  (no ACK webhook was pushed for API sends, as observed before); a delivered order could
  still communicate.
- Provider failure retained a `failed` order-linked row; after restarting WAHA the next send
  succeeded and history remained.
- Conversation timeline showed the order badge; phone-sent and conversation-composer
  messages remained unlinked.
- Log scan: no message bodies, order numbers, API keys or HMAC secrets.

## P08-W05 — Marketing Consent & Message Templates

Marketing consent and reusable templates are separate from operational order communication.
No campaigns, audience building, scheduling, throttling, capping or sending exist in this
package.

### Consent schema and semantics

`customers` gains `whatsapp_marketing_status` (`unknown` default, `subscribed`,
`unsubscribed`), `whatsapp_marketing_opted_in_at` and `whatsapp_marketing_opted_out_at`. No
consent-history table exists.

| Situation | Result |
|---|---|
| Unknown | Not marketing-eligible |
| Subscribed + syntactically usable number (`whatsapp` else `phone`, 6–15 digits) | Marketing-eligible |
| Subscribed without a usable number | Not eligible |
| Unsubscribed | Never eligible |

Transitions are centralized in a `Customer` `saving` hook so Admin edits and the inbound
opt-out share one rule: opt-in sets `opted_in_at` (preserving an older `opted_out_at`);
opt-out sets `opted_out_at` (preserving `opted_in_at`); re-subscription refreshes
`opted_in_at` and preserves the previous opt-out; reset to Unknown clears both; re-saving
the same status leaves timestamps untouched. Eligibility is a read-only method
(`canReceiveWhatsAppMarketing()`) and performs no provider verification — that belongs to
campaign send-time processing. Operational order/customer messaging never consults consent.

### Inbound keyword opt-out

Only a regular inbound `message` (`fromMe=false`) whose conversation is linked to a customer
can opt out, and only when the whole message matches one allowlisted keyword after
normalization (trim, collapse whitespace, lowercase English, remove Arabic
diacritics/tatweel, remove invisible Unicode format characters, normalize alef variants,
normalize Unicode spaces, strip surrounding punctuation/emoji):

`stop`, `unsubscribe`, `ايقاف الاشتراك`, `الغاء الاشتراك`, `لا اريد رسائل`, `لا اريد عروض`.

Bare «إلغاء» / «إيقاف» and order phrases such as «إلغاء الطلب» are explicitly not opt-outs.
No substring, fuzzy, NLP or AI matching exists. The inbound message is stored normally, the
conversation is untouched, no automatic reply is sent, and the log records only the customer
id and the new status (never the message body). Unlinked conversations mutate nothing;
`message.any source=app` and outbound/API messages never trigger the rule.

### Template schema, variables and renderer

`whatsapp_templates`: `id`, `name` (unique, ≤120), `type` (`marketing|general`), `body`
(text, ≤4096), `active` (default true), timestamps. No versions, approval workflows, folders,
localization tables, Meta ids, provider synchronization or WYSIWYG.

Approved variables: `{{customer_name}}`, `{{product_name}}`, `{{product_code}}`,
`{{business_name}}`. `business_name` resolves `settings.business_name` first and falls back
to `config('app.name')`. The renderer (`WhatsAppTemplateRenderer`) is deterministic: no
Blade/Twig/PHP/expression evaluation, unknown variables are rejected at save and at render,
missing product context fails closed, values are inserted as plain text (trimmed, control
characters stripped) and replacement is a single pass so inserted values are never
re-expanded. The 4096 limit is the existing provider-safe maximum.

### Admin UX

- Customer form: a compact **WhatsApp Marketing** section with the status select and
  read-only opt-in/opt-out timestamps; the Customer list shows a status badge.
- `WhatsApp → Templates`: list (name, type, active, updated at), create/edit/delete, a plain
  textarea body with the approved-variables helper, and a Preview action using real
  searchable Customer and optional Product records. Product becomes required when the
  template uses product variables. Preview is read-only, escaped and never sends.
- P08-W04 operational order messages stay code/localization-driven and were not migrated
  into `whatsapp_templates`.

### P08-W05 live QA (local MySQL, signed webhook)

- Migrations ran cleanly; guest access to `/admin/whatsapp-templates` redirected to the
  admin login.
- A signed inbound `message` with «إلغاء الاشتراك» on a linked conversation set the customer
  to Unsubscribed with an opt-out timestamp, stored the message and left the conversation
  linked; «إلغاء الطلب» and bare «إلغاء» stored messages without opting out; `stop` from an
  unlinked chat mutated no customer.
- Live transitions: subscribe set `opted_in_at` and preserved the previous opt-out; reset to
  Unknown cleared both; eligibility was true only while Subscribed with a usable number.
- `{{business_name}}` rendered the `settings.business_name` value while `app.name` was
  renamed in-process, and fell back to `config('app.name')` with no setting present.
- Log scan: no message bodies, template bodies, API keys or HMAC secrets; the opt-out log
  contains only `customer_id` and `status`.

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
