# R02 — Admin Order Operations

## Delegation Mode

You are responsible for **R02 — Admin Order Operations** in the Wholesale Order System.

Use this mandatory workflow:

1. **PLAN ONLY:** inspect the repository and accepted R01 implementation; submit a detailed impact assessment and execution plan without modifying files.
2. **WRITE only after explicit approval:** implement the approved plan, verify it, and stop for acceptance.

Your first response must contain planning only. Do not start coding or make repository changes.

## Prerequisite Gate

R01 must already be accepted. Verify, rather than assume, that the repository contains:

- statuses `new`, `confirmed`, `partially_delivered`, `delivered`, `cancelled`;
- order-item `delivered_quantity` and a trustworthy derived remaining quantity;
- optional product sizing controlled by a product-level flag;
- no stock-based customer-order blocking;
- stock hidden from customer-facing output.

If R01 is incomplete or inconsistent, stop and report the exact blocker. Do not repair R01 or expand this package without authorization.

Read the project documents and inspect all relevant Filament resources/pages/actions, policies, models, forms, tables, tests, and the accepted R01 diff. New client-review rules override older documents when they conflict.

## Objective

Create a clear, Arabic-friendly Filament operations workspace for reviewing, editing, confirming, partially delivering, completing, cancelling, and inspecting orders.

## Required Admin Experience

### 1. Order Management page

Create or revise the operational order-management experience with status-based tabs or equally clear Filament-native grouping:

- New / awaiting confirmation;
- Confirmed;
- Partially delivered;
- Delivered;
- Cancelled.

Each status view must show an accurate count and allow useful search/filtering without duplicating the source of truth.

### 2. Delivered Orders page

The current general `Orders` page is to become the delivered-orders history/reference view and must show only `status = delivered`.

The new Order Management page is the primary operational page for active review and status handling. Avoid confusing duplicate navigation or two pages that appear to own the same workflow.

### 3. New order operations

For a `new` order, Admin/Operations can:

- view full customer and order details;
- edit items before confirmation: product, color, conditional size, quantity;
- edit relevant order/customer/admin notes as permitted by current domain boundaries;
- confirm the order after review/contact;
- cancel the order if confirmation fails.

Edits must use current valid product/variant choices, preserve the correct snapshots, and recalculate derived totals. Stock quantity may be shown to Admin as reference but must not block the agreed order quantity.

### 4. Confirmed order operations

For a `confirmed` order, Admin can:

- view ordered, previously delivered, and remaining quantities per item;
- record a delivery quantity per item;
- mark all remaining quantities as delivered.

Direct free-form editing of the original ordered items after confirmation is outside scope unless explicitly approved. Delivery actions are the controlled mutation path.

### 5. Partial delivery interaction

The partial-delivery action must present each order item with:

- product and product code;
- color and size when applicable;
- ordered quantity;
- previously delivered quantity;
- deliver-now input;
- resulting remaining quantity.

Rules:

- deliver-now values must be non-negative integers;
- the action must deliver at least one unit overall;
- no item may exceed its remaining quantity;
- updates across all affected items and the order status must be atomic;
- if any quantity remains, final status is `partially_delivered`;
- if nothing remains, final status is `delivered`;
- repeated partial deliveries accumulate safely;
- cancelled, new, and delivered orders cannot use partial-delivery actions;
- double submission/concurrent updates must not over-deliver.

Do not create shipment, courier, dispatch, stock movement, payment, or invoice records. This is lightweight fulfillment tracking inside orders only.

### 6. Cancellation

Cancellation is a terminal operational path for an unconfirmed order when customer confirmation fails. Cancelled orders remain available for historical inspection but are excluded from active operations and production calculations.

The plan must define the allowed transition(s) based on current approved behavior. Do not invent broad restore/reopen flows.

### 7. Status actions and audit clarity

Only valid actions should be visible for the current state. Use clear confirmations for consequential actions. Reuse framework-native authorization and transactions.

If the current project has an audit mechanism, use it appropriately. Do not add a new generalized audit/event architecture solely for this package.

## Scope Boundaries

Do not:

- implement R03 dashboard/KPI/catalog work;
- implement R04 universal Excel export;
- add shipping/delivery-management tables or workflows;
- decrement/reserve stock;
- add payments, invoices, accounting, customer balances, client login, workflow engines, or a separate Vue Admin;
- rewrite working Filament features without demonstrated need.

Use Filament and the existing Laravel monolith. Keep code/schema names English and user-facing Admin labels/copy appropriate for Arabic operations where the current localization strategy supports it.

## Gate A — Required Planning Deliverable

Return a structured plan containing:

1. **Prerequisite verification** with repository evidence for the accepted R01 foundation.
2. **Current Admin inventory** — relevant navigation, resources, pages, tables, forms, actions, policies, statuses, tests, and current Orders behavior.
3. **UX/navigation design** — exact pages, labels, tabs, counts, columns, filters, empty states, row/header/bulk actions, and how Delivered Orders and Order Management avoid overlap.
4. **Transition/action matrix** — for every status, list allowed actions, denied actions, resulting status, and validation rules.
5. **Editing design** — fields editable in `new`, snapshot updates, optional-size behavior, total recalculation, validation, authorization, and transaction boundary.
6. **Partial-delivery design** — per-item calculations, atomic update approach, concurrency/idempotency protection, automatic status resolution, and error handling.
7. **Query/performance plan** — counts, eager loading, pagination, indexes already present or genuinely required, and avoidance of N+1 queries.
8. **Implementation file map** — exact files to create/modify with purpose.
9. **Test plan** — happy paths, invalid transitions, over-delivery, repeat delivery, zero submission, mixed-item delivery, full completion, cancellation, edit restrictions, authorization, concurrency where practical, and regressions.
10. **Documentation delta, risks, rollback, commands, and ordered execution steps.**
11. **Questions/blockers** that cannot safely be decided from repository evidence.

## Gate A Stop Condition

After submitting the plan, make no edits, migrations, commits, or R03 changes. Wait for explicit approval and WRITE authorization.

## Gate B — Execution Contract (Only After Approval)

Implement only the approved R02 plan. Keep changes minimal and preserve unrelated work.

At handoff, provide:

- implemented behavior and changed files;
- migration/data notes, if any were approved;
- focused test and regression results;
- formatting/static-analysis results;
- manual verification steps for every status path;
- screenshots only if the established project workflow uses them;
- unresolved risks or deviations;
- a clear stop awaiting R02 acceptance before R03.

## R02 Acceptance Criteria

R02 is complete only when:

- Order Management provides accurate state-based operational views and counts;
- the old Orders area acts as delivered-order history only;
- new orders can be safely edited, confirmed, or cancelled;
- confirmed orders can record partial or complete delivery;
- delivered quantities never become negative or exceed ordered quantities;
- status automatically reflects the actual remaining quantities;
- invalid actions are unavailable and rejected server-side;
- concurrent/repeated submissions cannot over-deliver;
- cancelled orders remain historical and outside active operations;
- no inventory, shipping, accounting, or unrelated dashboard/export scope is added;
- required tests and project checks pass.
