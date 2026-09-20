# R03 — Operations Dashboard

## Delegation Mode

You are responsible for **R03 — Operations Dashboard** in the Wholesale Order System.

The first response is **PLAN ONLY**. Inspect the repository and submit the required plan without editing files. Implementation begins only after explicit owner approval and WRITE authorization.

## Prerequisite Gate

R01 and R02 must be accepted. Verify their implementation from repository evidence, including lifecycle states, delivered/remaining quantity rules, pending-order edits, and delivery actions. If those foundations are incomplete, report the blocker and stop; do not absorb their work into R03.

Read the full project documentation and inspect the current Filament dashboard/widgets/pages, order queries, models, relationships, navigation, tests, and accepted R01/R02 work. Client-review rules in this assignment override conflicting older dashboard requirements.

## Objective

Turn the Filament Admin dashboard into a practical operations control center that shows current order workload and outstanding production quantities, with every operational number traceable to its source records.

## Required Dashboard Capabilities

### 1. KPI cards

Provide clear operational cards for at least:

- New orders awaiting confirmation;
- Confirmed orders;
- Partially delivered orders;
- Outstanding quantity;
- Delivered orders;
- Active products.

Retain Today's Orders only if repository/product evidence shows it remains useful and the plan states its exact definition.

### 2. KPI definitions

Use explicit, testable definitions:

- **New Orders:** count of `new` orders.
- **Confirmed Orders:** count of `confirmed` orders.
- **Partially Delivered Orders:** count of `partially_delivered` orders.
- **Delivered Orders:** count of `delivered` orders.
- **Active Products:** count according to the existing product active flag.
- **Outstanding Quantity:** sum of item remaining quantities only for orders in `confirmed` or `partially_delivered`.

For every included order item:

```text
remaining = quantity - delivered_quantity
```

`new`, `cancelled`, and `delivered` orders must not contribute to Outstanding Quantity or current production requirements.

### 3. Clickable source navigation

Each operational KPI must link to the relevant source table/view with the corresponding filter already applied wherever meaningful.

Examples:

- New Orders → Order Management filtered to `new`.
- Confirmed Orders → filtered to `confirmed`.
- Partially Delivered → filtered to `partially_delivered`.
- Delivered Orders → Delivered Orders history.
- Active Products → Products filtered to active.
- Outstanding Quantity → a transparent source view of contributing items/orders.

The displayed number and source view must use equivalent criteria so users can reconcile them.

### 4. Current production requirements catalog

Add a professional product-card/catalog section for products that currently have outstanding quantities.

Each card should show, using existing assets where available:

- product image;
- product name;
- product code;
- total quantity required now;
- a clear link/action to inspect contributing orders.

Product required quantity is:

```text
SUM(order_items.quantity - order_items.delivered_quantity)
```

grouped by product, and restricted to parent orders with status `confirmed` or `partially_delivered`.

Products with no positive outstanding quantity are not shown in this section.

### 5. Product drill-down

Clicking a product's required quantity or source action must open a source table that shows the contributing records, including:

- order number;
- customer/store identity appropriate to existing data;
- product code/name;
- color;
- size only when applicable;
- ordered quantity;
- delivered quantity;
- remaining quantity;
- order status;
- navigation to the individual order.

The drill-down total must reconcile exactly with the product card.

### 6. Data integrity and freshness

The dashboard is operational, not advanced analytics. Prefer clear database queries and existing framework primitives. Avoid stale cache unless the repository already has an approved invalidation strategy and the plan proves it is needed.

No values may be independently stored merely for dashboard display. Counts and remaining quantities come from source order/product data.

## Scope Boundaries

Do not add:

- charts or advanced analytics not required here;
- forecasting, production scheduling, manufacturing orders, stock planning, or inventory transactions;
- shipping, payments, accounting, or customer credit;
- new data warehouse/reporting architecture;
- Redis, queues, materialized summaries, or cached counters without explicit approval;
- R04 universal exports, except designing source tables so R04 can later add export actions cleanly;
- R05 final QA/documentation closure.

Use Filament within the current Laravel monolith. Maintain Arabic-friendly operational UX and responsive behavior.

## Gate A — Required Planning Deliverable

Return a structured plan with:

1. **Prerequisite verification** for R01/R02 with paths and current behavior.
2. **Current dashboard assessment** — existing widgets/cards/queries/navigation and what will be retained, replaced, or removed.
3. **Metric contract table** — KPI name, exact formula, included statuses, excluded statuses, date/timezone rules if applicable, source tables, and destination link/filter.
4. **Production-requirements query design** — aggregation grain, joins, null handling, positive-remaining filtering, product image selection, snapshot/live-product considerations, and SQL/Eloquent approach.
5. **Drill-down reconciliation design** — exact filters/columns/navigation and how totals remain consistent with cards and KPIs.
6. **UI composition** — Filament widgets/pages/components, ordering, responsive layout, empty/loading/error states, Arabic labels, and accessibility considerations.
7. **Performance assessment** — expected query count, indexes, pagination, eager loading, N+1 avoidance, and bounded result rendering. Do not propose premature infrastructure.
8. **Implementation file map** — exact files to create/modify and purpose.
9. **Test plan** — formula/unit/integration tests with mixed statuses, partial quantities, cancelled/new exclusions, fully delivered exclusions, zero outstanding, multiple colors/sizes, deleted/inactive product edge cases, drill-down reconciliation, links/filters, and authorization.
10. **Documentation delta, risks, rollback, verification commands, and execution order.**
11. **Questions/blockers** only where owner input is genuinely required.

Include at least one worked fixture example demonstrating expected KPI, product aggregate, and drill-down results from the same data.

## Gate A Stop Condition

After the plan, do not edit files, commit, or begin R04. Wait for explicit approval and WRITE authorization.

## Gate B — Execution Contract (Only After Approval)

Implement only the approved plan. Keep queries and UI simple, traceable, and maintainable.

At handoff, report changed files, exact metric definitions implemented, tests and commands with results, query/performance observations, manual reconciliation evidence, and any deviations. Stop for R03 acceptance before R04.

## R03 Acceptance Criteria

R03 is accepted only when:

- KPI values follow the approved formulas and link to matching sources;
- outstanding quantities include only confirmed and partially delivered remaining units;
- production cards aggregate accurately by product;
- every product total reconciles with its drill-down rows;
- new, cancelled, and delivered orders are excluded from current production needs;
- navigation, empty states, RTL/Arabic operational usability, and responsive layout are sound;
- query behavior is bounded and avoids obvious N+1 problems;
- no advanced analytics, inventory, manufacturing, or unrelated scope is introduced;
- required tests and checks pass.
