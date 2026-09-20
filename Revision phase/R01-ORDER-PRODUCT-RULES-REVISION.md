# R01 — Order & Product Rules Revision

## Delegation Mode

You are the implementation agent responsible for **R01 — Order & Product Rules Revision** in the Wholesale Order System.

This assignment uses a mandatory two-gate workflow:

1. **Gate A — PLAN ONLY:** inspect the repository and submit the required impact assessment and execution plan. Do not modify files, generate migrations, or write implementation code.
2. **Gate B — WRITE:** begin implementation only after the project owner explicitly approves the plan and authorizes execution.

Your first response to this assignment must be the Gate A planning deliverable only. Stop after submitting it.

## Authoritative Context

Read these project documents before planning:

- `README.md`
- `01-PRD.md`
- `02-IMPLEMENTATION-BLUEPRINT.md`
- `03-WORK-PACKAGES.md`
- `04-DEVELOPMENT-GUARDRAILS.md`
- `05-DATABASE-SCHEMA.md`
- `06-MVP-ACCEPTANCE.md`

Also inspect the current implementation, migrations, models, enums/constants, requests, controllers, Vue/Inertia pages and components, Filament resources/pages/actions, imports/exports, tests, factories, seeders, and any relevant localization files.

The client-review decisions in this assignment are the newest approved business source. They override older documentation wherever a conflict exists. Do not silently resolve unrelated inconsistencies; report them.

## Objective

Revise the core order and product rules so the system supports the approved post-MVP-review workflow while remaining a lean Laravel monolith.

## Approved Business Rules

### 1. Order lifecycle

The supported statuses are:

- `new` — submitted and awaiting operations review.
- `confirmed` — reviewed and approved by Admin/Operations.
- `partially_delivered` — part of the order has been delivered.
- `delivered` — the complete order has been delivered.
- `cancelled` — confirmation failed or the order was cancelled; terminal and excluded from active operational calculations.

`exported` is no longer an order status. Excel export is an action, not a lifecycle state.

Planning must audit current records and all usages of the old statuses. Propose a safe, explicit treatment for any existing `exported` data; do not guess, destroy, or silently remap production data.

### 2. Delivered and remaining quantities

Each order item must support a non-negative `delivered_quantity`, initially `0`.

The remaining quantity is derived, not independently stored:

```text
remaining_quantity = quantity - delivered_quantity
```

Invariant:

```text
0 <= delivered_quantity <= quantity
```

Only `confirmed` and `partially_delivered` orders contribute to current outstanding production requirements. `new`, `cancelled`, and `delivered` orders do not.

R01 establishes the domain/schema rules and critical tests. The complete Filament delivery-operation UX belongs to R02.

### 3. No stock-based ordering restriction

The customer may request any positive quantity. The system must not compare the requested quantity with `available_quantity` to block:

- variant selection;
- adding to cart;
- updating cart quantity;
- checkout;
- final order creation.

Operations may contact the customer, edit the pending order, and confirm the agreed quantities later.

`available_quantity` remains an internal Admin reference only. This change does not authorize stock movements, reservations, decrements, warehouse transactions, or inventory accounting.

### 4. Stock privacy

Customer-facing pages, Inertia props, HTML, JavaScript state, public responses, validation messages, and page source must not expose `available_quantity` or exact stock levels.

Admin users may continue to see and manage `available_quantity`.

### 5. Quantity selector

The customer-facing product ordering UI must provide:

- preset `5`;
- preset `10`;
- `Other quantity`, which reveals a positive-integer input.

Backend validation remains authoritative. Quantity must be a positive integer, but it is not capped by stock.

### 6. Optional size selection per product

Do not remove size support.

Add a product-level boolean such as `size_enabled` with default `false`:

- When disabled, the customer selects color and quantity only; no size selector is rendered or required.
- When enabled, the customer selects color, size, and quantity.
- The option is off by default in Admin create/edit forms.
- Existing size-enabled data must be preserved safely.

Do not create fake values such as `N/A`. A variant may have no size only when the product has size selection disabled. When size is enabled, size is required.

Planning must explicitly address database uniqueness for both sized and unsized variants. Be careful: ordinary nullable composite unique constraints in MySQL can allow duplicate rows containing `NULL`.

### 7. Pending-order editability foundation

Operations must be able to edit products, colors, optional sizes, quantities, and notes while an order is `new`, before confirmation. R01 should establish or protect the underlying rules and recalculations needed for this; the full Admin page/actions belong to R02.

Any derived order totals must remain correct after an allowed edit.

## Scope Boundaries

Do not introduce:

- inventory reservations or automatic stock deduction;
- warehouse or stock-movement tables;
- shipping, courier, shipment, or delivery-management subsystems;
- payments, invoices, accounting, customer balances, or credit;
- client authentication;
- workflow engines, microservices, CQRS, DDD layers, or speculative abstractions;
- R02 Admin operations UI, R03 dashboard, R04 universal exports, or R05 final QA work beyond compatibility needed for R01.

Use Laravel + Vue 3 + Inertia + MySQL + Tailwind + Filament within the existing monolith. Preserve product/customer accounting codes, order-item snapshots, Arabic-first RTL storefront behavior, and hidden-price security.

## Gate A — Required Planning Deliverable

Return one structured plan containing all of the following:

1. **Repository evidence** — current files/classes/tables/routes/components/tests involved, with paths and a brief description of current behavior.
2. **Gap and impact assessment** — every conflict between current behavior/docs and the approved R01 rules.
3. **Data audit plan** — queries/checks for existing statuses, delivered quantities, size data, duplicate-risk combinations, cart/session shape, and existing records affected by migrations.
4. **Proposed schema and migration strategy** — exact columns/indexes/constraints, defaults/nullability, reversible migration considerations, and safe handling of legacy data. Flag any business decision that cannot be inferred safely.
5. **Domain/state rules** — proposed transition matrix, invariants, authorization/editability rules, recalculation behavior, and where each rule will be enforced.
6. **Implementation file map** — files to create/modify and why. Separate required changes from optional suggestions.
7. **Test plan** — focused automated tests covering status rules, delivered quantity invariants, unlimited requested quantity, stock non-disclosure, quantity presets/custom input, conditional sizing, uniqueness, legacy compatibility, and order total recalculation.
8. **Documentation delta** — exact sections that will eventually need updates. Do not edit them during Gate A.
9. **Risks and rollback** — migration/data risks, compatibility concerns, security/privacy risks, and rollback approach.
10. **Execution sequence and verification commands** — ordered, bounded steps for Gate B.
11. **Questions/blockers** — only decisions that genuinely require owner input; otherwise state assumptions explicitly.

Do not estimate by guesswork. Base the plan on inspected repository evidence.

## Gate A Stop Condition

After delivering the plan:

- do not edit any file;
- do not run migrations that mutate data;
- do not commit;
- do not begin R02;
- wait for explicit approval and WRITE authorization.

## Gate B — Execution Contract (Only After Approval)

After approval, implement only the approved plan and approved amendments. Preserve unrelated user changes and keep the diff minimal.

Before handoff:

- run the relevant focused tests, then the appropriate regression suite;
- run project formatting/static checks that already exist;
- inspect customer-facing payloads/source to verify stock is not leaked;
- report migrations and any data decision requiring deployment care;
- report changed files, commands run, results, and remaining risks;
- do not claim PASS if a required check was skipped or failed;
- stop after R01 and request acceptance before R02.

## R01 Acceptance Criteria

R01 is acceptable only when:

- approved statuses are represented consistently and `exported` is no longer used as an active lifecycle status;
- `delivered_quantity` is safe and remaining quantity is derived correctly;
- the storefront accepts any positive requested quantity without stock blocking;
- exact stock is inaccessible from customer-facing payloads/source/messages;
- quantity choices `5`, `10`, and custom positive integer work correctly;
- size is optional by product, defaults off, and existing legitimate size data is preserved;
- sized/unsized variant uniqueness is reliable in MySQL and application validation;
- pending-order edits can be supported without corrupting snapshots or totals;
- no prohibited inventory, shipping, accounting, or architecture scope has been added;
- required tests pass.
