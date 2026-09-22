# Wholesale Order System — Work Packages

Execution must be sequential.

Do not begin the next package until the current package passes its acceptance criteria.

---

# Phase 00 — Foundation

## P00-W01 — Project Bootstrap

### Scope

Create and configure:

- Laravel
- Vue 3
- Inertia.js
- MySQL
- Tailwind CSS
- Vite
- Filament
- Admin authentication
- Arabic / RTL frontend foundation

### Acceptance

- Application runs successfully.
- Vue 3 renders through Inertia.
- Database connection works.
- Filament Admin login works.
- Storefront base layout renders correctly.
- No business features implemented.

---

# Phase 01 — Core Data

## P01-W01 — Categories & Products

### Scope

Implement:

- Categories
- Products
- Product Images
- Relationships
- Filament CRUD

### Acceptance

- Admin can create/edit products.
- Product Code is required and unique.
- Products can be active/inactive.
- Product can have multiple images.

---

## P01-W02 — Product Variants

### Scope

Implement:

- Color
- Size
- Available Quantity

### Acceptance

- Admin can manage multiple variants per product.
- `product_id + color + size` is unique.
- Negative quantities are rejected.

---

## P01-W03 — Customers

### Scope

Implement customer entity and Filament management.

### Acceptance

Admin can:

- Create customer.
- Edit customer.
- Search by name.
- Search by phone.
- Search by Customer Code.

---

# Phase 02 — Storefront

## P02-W01 — Product Catalog

### Scope

Vue 3 storefront:

- Product grid
- Search
- Category filter
- Pagination

### Acceptance

- Only active products are visible.
- Catalog works on mobile.
- Search works.
- Category filter works.

---

## P02-W02 — Product Details

### Scope

Implement:

- Product gallery
- Product code
- Product information
- Price visibility
- Variant selection
- Quantity availability

### Acceptance

- Public price is visible.
- Hidden price is not exposed in frontend props/source.
- Available colors/sizes are correct.

---

## P02-W03 — WhatsApp Price Request

### Scope

Implement request price button.

Message includes:

- Product Name
- Product Code
- Product URL

### Acceptance

- Correct WhatsApp number is used.
- Correct product data is included.
- No internal price request workflow is added.

---

# Phase 03 — Ordering

## P03-W01 — Cart

### Scope

Session-based cart.

Implement:

- Add
- Update quantity
- Remove
- Clear
- Cart totals where applicable

### Acceptance

- Quantity > 0 (no stock-based cap; updated by Revision R01).
- Cart survives standard navigation.
- No database cart tables.

---

## P03-W02 — Customer Capture

### Scope

Checkout customer form.

Matching rule:

- Match existing customer by mobile.
- Otherwise create customer.

### Acceptance

- Customer data is saved.
- Existing customer can be reused.
- No password/login account created.

---

## P03-W03 — Order Creation

### Scope

Implement:

- Orders
- Order Items
- Order numbering
- Snapshots
- Transactional creation
- Server-side revalidation

### Acceptance

- Empty cart rejected.
- Quantity is revalidated on server.
- Order number generated.
- Product snapshots stored.
- Correct customer linked.

---

## P03-W04 — Order Success

### Scope

Vue success page.

### Acceptance

Displays:

- Success message
- Order Number
- Contact/next-step message

---

# Phase 04 — Admin Orders

## P04-W01 — Order Management

### Scope

Filament order management.

### Acceptance

Admin can:

- List orders.
- Filter orders.
- Open order details.
- View customer information.
- View order items.

---

## P04-W02 — Order Status

### Scope

Statuses (Revision R01):

- new
- confirmed
- partially_delivered
- delivered
- cancelled

`exported` is no longer a lifecycle status (export is an action; Revision R01). Any pre-revision `exported` behavior is superseded.

### Acceptance

- Admin can update status through approved server-side transitions only.
- No workflow engine added.
- A dedicated review-and-confirm page (`/admin/order-management/{id}/confirm`) shows the full
  order, its notes and the customer data before confirming.
- Confirming saves `status = confirmed` on the orders table and stores optional admin
  notes. Confirmation has no stock effect (Revision R01); `available_quantity` never gates
  an order.
- Confirmed orders cannot be cancelled; they progress through delivery actions
  (`partially_delivered` → `delivered`). Pending `new` orders can be cancelled.

---

# Phase 05 — Import / Export

## P05-W01 — Customer Import

### Scope

Excel customer import.

### Acceptance

- Customer Code supported.
- Duplicate codes handled safely.
- Invalid rows reported.
- Valid rows imported.

---

## P05-W02 — Product Import

### Scope

Excel product import.

### Acceptance

- Product Code is matching key.
- Existing products can be updated.
- New products can be created.
- Invalid rows reported.

---

## P05-W03 — Order Export

### Scope

Excel export for one or multiple orders.

### Acceptance

- One row per order variant.
- Product Code preserved.
- Customer Code preserved.
- Required columns exported.
- Deferred (Revision R01): order export is not implemented; `exported` is not a status.
  Revision R04 owns universal exports, which must never mutate order status.

---

# Phase 06 — Finalization

## P06-W01 — Arabic / RTL Finalization

### Scope

Finalize storefront Arabic UX.

### Acceptance

- RTL works correctly.
- Mobile flow is usable.
- Main client-facing copy is Arabic.

---

## P06-W02 — MVP QA

### Scope

Verify complete flow:

Catalog  
→ Product  
→ Cart  
→ Customer  
→ Order  
→ Admin  
→ Export

### Acceptance

No blocker remains in the core MVP flow.

No new business features should be added in this package.

---

# Phase 07 — Storefront Experience

Brand: **MAI SHOES**. Bold retro identity — Crimson `#C91424`, Burgundy `#8F0808`,
Deep Navy `#073F57`, Powder Blue `#75A9C7`, Cream `#FFF0D3`.

Semantic tokens are declared once in `resources/css/app.css` (`@theme` + `.dark`).
Components use semantic utilities only (`bg-surface`, `text-ink`, `border-line`, …).

## P07-W01 — Design Foundation

### Scope

- Semantic color tokens for light and dark mode.
- Arabic webfonts (IBM Plex Sans Arabic, Reem Kufi, Lalezar, Archivo Black).
- Global focus ring, reduced-motion guard, tabular numerals.
- Root template: Open Graph tags from `props.meta`, theme color, SVG favicon,
  pre-paint theme script (no flash of wrong theme).

### Acceptance

- Storefront renders `lang="ar" dir="rtl"`.
- OG tags and product OG image appear in server-rendered HTML (WhatsApp sharing).
- Dark mode applies without a flash on load.

## P07-W02 — UI Primitives

### Scope

Reusable components with no new dependencies: button, icon button, input, select,
textarea, badge, card, quantity stepper, toast host, confirm dialog, skeleton,
empty state, breadcrumbs, price tag, inline SVG icon set.

### Acceptance

- Every interactive control is at least 44px tall.
- Icon-only controls expose an accessible label.
- No emoji used as icons.

## P07-W03 — Shell & Feedback

### Scope

Sticky header with active navigation, cart badge and mobile menu; footer with
WhatsApp contact; skip link; flash messages shared through Inertia; add-to-cart
returns to the previous page with a confirmation toast.

### Acceptance

- Add to cart no longer navigates away from the catalog/product page.
- Flash success/info/error messages surface as toasts.
- Header navigation is reachable by keyboard with visible focus.

## P07-W04 — Catalog

### Scope

Search with icon, debounce and clear; category chips with counts; results count;
skeleton loading grid; color indicators on cards (stock indicators removed by
Revision R01 — stock privacy); numbered pagination with scroll reset; empty state
with filter reset.

### Acceptance

- Hidden prices remain excluded from catalog payloads.
- Filtering and pagination work with browser back/forward.
- Mobile grid stays readable at 375px.

## P07-W05 — Product Details

### Scope

Breadcrumbs, sticky buy box, native-radio variant selection without stock labels
(stock privacy per Revision R01), quantity stepper, add-to-cart loading state,
prominent WhatsApp price CTA, related products, product OG metadata.

### Acceptance

- Product options are visible and selectable on mobile without hover.
- Color (and size when the product enables sizes) must be selected before adding to the order.

## P07-W06 — Cart & Checkout

### Scope

Cart rows with thumbnails, per-line quantity errors, confirm dialogs for remove
and clear, sticky order summary. Checkout uses `useForm` with field-level errors,
error summary with focus move, autocomplete/inputmode attributes, governorate
suggestions, submit loading state and non-payment notice.

### Acceptance

- Destructive cart actions require confirmation.
- Quantity errors appear next to the affected line.
- Submitting twice is not possible while a request is in flight.

## P07-W07 — Home & Order Success

### Scope

Real landing page (hero, stats, category tiles, latest products, how-it-works,
assurances) backed by `HomeController`. Order success page with order number,
copy action, next steps and WhatsApp handoff.

### Acceptance

- Home shows only active products and non-empty categories.
- Order number can be copied and sent over WhatsApp.

## P07-W08 — Admin Branding (Arabic / RTL)

### Scope

Filament panel branded as MAI SHOES: logo, favicon, Arabic font, crimson palette,
SPA mode, top navigation, Arabic locale (RTL) and an operational dashboard widget
(new orders, today's orders, confirmed orders, active products, customers).

### Acceptance

- `/admin` renders with `dir="rtl"`.
- Dashboard exposes the operational counters required by the PRD.

## P07-W09 — QA

### Acceptance

- `php artisan test` passes.
- `npm run build` passes.
- Storefront pages return 200 with and without cart contents.
- Request-price products never leak their price into HTML or props.

---

# Revision Phase R01–R05 (approved)

The client-review revision phase follows Phase 07. Where any earlier statement in this
document conflicts with this section, this section supersedes it. Authoritative assignment
documents live in `Revision phase/`.

- **R01 — Order & Product Rules Revision: implemented.** Statuses `new`, `confirmed`,
  `partially_delivered`, `delivered`, `cancelled`; `exported` removed as a status; no
  stock-based ordering restriction; stock privacy; quantity presets 5/10/custom; optional
  per-product sizes; delivered quantity and remaining quantity; pending-order editability
  foundation.
- **R02 — Admin Order Operations: implemented.** Order Management status tabs, Delivered
  Orders history, pending-order editing, confirmation/cancellation, partial and complete
  delivery recording with concurrency protection.
- **R03 — Operations Dashboard: implemented.** Operational KPI cards on the Filament
  dashboard (new, today by Cairo business day, confirmed, partially delivered, delivered,
  outstanding production quantity, active products, customers) with every card linking to
  its matching source view. Authoritative production rules: production statuses are
  `confirmed` + `partially_delivered` only; outstanding quantity is
  `SUM(order_items.quantity - order_items.delivered_quantity)`; the dashboard production
  requirements catalog aggregates outstanding quantity at product level; drill-down is at
  order-item/variant level on `/admin/production-requirements` (one row per ordered
  variant, optionally filtered by `?product=`), and product card totals reconcile exactly
  with their drill-down rows.
- **R04 — Universal Admin Excel Export: implemented.** Synchronous XLSX exports (Maatwebsite
  Excel, no queue or migration) on every Admin business table: Products, Customers,
  Categories, Variant Colors, Variant Sizes, Product Variants, Order Management, Delivered
  Orders and the R03 production requirements drill-down, plus single-order export from the
  order view pages. Order exports are at order-item grain (one row per ordered variant from
  the immutable snapshots: order number, customer code/name/phone, product code/name, color,
  optional size, ordered/delivered/remaining quantity, status, unit price). Table header
  actions export all rows matching the current tab/filters/search; bulk actions export the
  selected rows; the drill-down export respects `?product=`. Identifiers, phones and leading
  zeros are written as text cells and spreadsheet formula injection is neutralized. Exports
  are read-only and never change order lifecycle state (`exported` remains removed).
- **R05 — Regression, Documentation & Revised MVP Acceptance: pending.**
- **R06 — Product & Variant Management UX Revision: implemented.** Admin-only UX revision.
  (1) Order Items repeater on the order edit page now
  renders as a table (Product 32% / Variant 48% / Quantity 15%) with the delete action at
  row end; on narrow containers it stacks with per-field labels. Order rules, snapshots,
  eligibility and quantity authority are unchanged. (2) The Products list exposes the
  existing create page through an `إضافة منتج` action. (3) Variant Colors and Variant Sizes
  are grouped under an `الإعدادات` navigation group with the general settings page; routes
  and data models are unchanged. (4) Variant generation: active Variant Sizes are the
  fixed five-size template (no `default` column); an `إضافة لون بالمقاسات الافتراضية`
  action on the Variants relation manager accepts per-color default quantities and
  creates every missing Product + Color + Size combination inside one atomic
  transaction. The default quantity is copied into new variants only (never a shared live
  quantity); existing variants are never updated, regenerated or deleted, and changing
  global sizes never mutates historical products. Every new color generates the five
  active size variants even when customer size choice is disabled. Individual quantities are edited independently through an
  inline `TextInputColumn` with server-side column rules, with `ProductVariant::validate()`
  and the `(product_id, color, size_key)` unique index remaining authoritative for every
  generated record. The active size lookup must contain exactly five sizes before a new
  color can be generated.
- **Out-of-package — Wholesale selection rules (user-approved): implemented.** Product
  variants retain their available colors and optional sizes independently from the storefront
  choice toggles. Disabled color choice displays and orders the complete set; enabled color
  choice supports multiple colors; disabled size choice displays assigned sizes without asking
  the customer to choose; size choice requires color choice. Requested quantity is stored
  per color. Actual pieces equal requested quantity multiplied by the number of selected
  colors (or every available color when color choice is disabled), and a public line total
  equals actual pieces multiplied by unit price. Sizes do not multiply the total, but when
  size choice is disabled the quantity per color must divide evenly by every selected
  color's available size count. Order items store requested quantity, color count, and the
  derived actual-piece quantity; pre-migration historical totals are preserved.

---

# Phase 08 — WhatsApp Communication & Marketing

Branch ownership: all Phase 08 work is committed only to `feature/p08-whatsapp-module`.
`staging` and `main` must not receive Phase 08 commits until explicit merge approval.
Integration document: `10-WHATSAPP-INTEGRATION.md`.

## P08-W01 — WhatsApp Provider Spike & Foundation

### Scope

- WAHA CORE as the single approved provider, isolated behind
  `App\Contracts\WhatsAppGateway`.
- Local WAHA container for the spike (`deploy/waha/docker-compose.yml`).
- Session creation/status, QR retrieval, outbound text, outbound media, health checking.
- Inbound webhook endpoint with HMAC-SHA512 verification, throttling, duplicate/replay
  protection and minimal non-PII logging.
- Minimal configuration and automated Laravel-side tests.
- Manual spike scenarios and the production/staging hosting requirement.

### Acceptance

- WAHA runs locally with persistent session storage.
- The 14 spike scenarios are executed and recorded.
- Real `session.status`, `message` and `message.ack` payload shapes are captured and the
  idempotency key is confirmed against observed data.
- Laravel-side tests pass; no secrets or QR/auth material appear in logs.
- No inbox, conversations, customer linking, templates, consent, campaigns, scheduling,
  throttling or campaign-safety features are implemented.
- No queues, Redis, jobs or workers are introduced.

### P08-W01A — Live Pairing & End-to-End Verification

Completed after P08-W01: a real WhatsApp account was paired on WAHA `2026.9.1` (engine
`NOWEB`) and all 14 scenarios passed, including outbound text/media through the Laravel
gateway, inbound webhook delivery, `SERVER`/`DEVICE` acknowledgements, session persistence
across container restart and recreate, a real unlink/re-pair cycle and duplicate webhook
protection with a captured event. Live verification exposed and fixed one defect: WAHA
`NOWEB` send responses carry the message id in `key.id` and the timestamp in
`messageTimestamp`, which `SentMessage` now handles with a regression test. Details and
observed payload shapes are recorded in `10-WHATSAPP-INTEGRATION.md`.

## P08-W02 — WhatsApp Account & Sessions

### Scope

- Filament page **Account** under the new **WhatsApp** navigation group, for the single
  session defined by `config('whatsapp.session')` (`WHATSAPP_SESSION`).
- Separate WAHA service health and WhatsApp account status cards, with business-friendly
  state labels and operator guidance; raw provider status kept only as a diagnostic line.
- Actions: Refresh, Start, Stop, Restart, Logout/Unlink (confirmed) and Create & Start.
- Authenticated on-demand QR route for the configured session.
- Gateway addition: `restartSession(string $name): void`.

### Acceptance

- Config-only session management: no `whatsapp_accounts` table, and no credentials, QR
  data or provider session state persisted in the database.
- Actions appear only in valid states and are re-checked against the live provider state
  before execution.
- QR is fetched on demand, never logged and never persisted; it is served only to
  authenticated admins and only for the configured session, with `Cache-Control: no-store`.
- Logout requires explicit confirmation and is never used as an automatic recovery action.
- Automated coverage for authorization, status mapping, every action, QR behavior,
  sanitized provider errors and the disabled kill switch; full suite green.
- Live QA on the paired local WAHA environment passes (connected, restart, stop, start,
  logout, QR re-pair, WAHA unavailable/recovery, disabled, Arabic/RTL, log scan).

## P08-W03 — WhatsApp Inbox & Conversations

### Scope

- Persistent `whatsapp_conversations` and `whatsapp_messages`, plus an index on
  `customers.whatsapp`; one-to-one chats only.
- Inbound ingestion from the signed webhook (`message`), phone-sent outbound history
  (`message.any`, `fromMe=true`, `source=app`) and acknowledgement updates
  (`message.ack`).
- Outbound manual text replies from the conversation view with a pending → sent/failed
  lifecycle; no queue and no media storage.
- Verified identity resolution (`@c.us` directly, `@lid` through the WAHA LIDs API when
  available) and deterministic customer matching; manual link/change/unlink.
- Read-only Filament Inbox list and conversation detail with an escaped message timeline.

### Acceptance

- Duplicate webhook deliveries and duplicate provider message tokens never create duplicate
  rows; ingestion is idempotent and releases the replay reservation when processing fails.
- `message.any source=api` and inbound `message.any` events are ignored; phone-sent
  messages are stored exactly once.
- Statuses move monotonically (PENDING/SERVER/DEVICE/READ/ERROR), unknown ack names are
  preserved without changing status, and no behavior depends on READ.
- `@lid` chats without a verified mapping stay unlinked and remain fully usable; no phone
  is ever inferred and no customer is auto-created.
- Message bodies are rendered escaped, never logged, and never stored as raw provider
  payloads; groups/broadcasts are ignored.
- Automated coverage for ingestion, dedupe, `message.any`, ack mapping, matching,
  authorization, escaping, outbound lifecycle and disabled integration; full suite green.
- Live QA on the paired local WAHA environment passes.
