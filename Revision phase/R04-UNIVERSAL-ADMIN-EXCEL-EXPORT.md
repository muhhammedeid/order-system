# R04 — Universal Admin Excel Export

## Delegation Mode

You are responsible for **R04 — Universal Admin Excel Export**.

Your first response must be **PLAN ONLY**. Inspect the repository and submit an evidence-based plan. Do not modify files until the project owner explicitly approves the plan and authorizes WRITE execution.

## Prerequisite Gate

R01, R02, and R03 must be accepted. Verify the actual repository state. If a prerequisite is missing, report it and stop rather than implementing another phase's work.

Read the project documents and inspect the current Excel package/configuration, exports/imports, Filament resources/pages/tables, filters, policies, tests, and the R03 drill-down views. New client-review requirements override older rules that treated export as an order status.

## Objective

Add a consistent Excel export capability to every meaningful Admin data table while preserving the table's current scope, filters, privacy rules, and accounting identifiers.

## Required Export Coverage

At minimum, audit and plan export support for:

- Products;
- Customers;
- Order Management;
- Delivered Orders;
- Dashboard source/drill-down tables;
- Product outstanding-quantity source orders;
- every other existing Admin table backed by real business records.

Small presentational tables, form repeaters, relation summaries, or non-record UI fragments do not automatically require export. The Gate A table inventory must explicitly classify every Admin table as included or excluded, with a reason.

## Export Behavior

### 1. Current view semantics

Exports must respect the intended table scope and active user filters/search where technically supported and appropriate. A source drill-down export must represent the same contributing rows the user is viewing.

State clearly whether an action exports:

- selected rows;
- all rows matching current filters;
- or both.

Avoid misleading actions whose output silently ignores filters.

### 2. Data structure

Use readable, stable headers and one business record per row at the correct grain.

For order-item-oriented exports, preserve at least the relevant:

- order number;
- customer code when available;
- customer/store identity as approved;
- product code snapshot;
- product name snapshot;
- color;
- size only when applicable;
- ordered quantity;
- delivered quantity;
- remaining quantity;
- status;
- price fields only where already authorized.

Do not expose hidden internal prices to unauthorized/public contexts. These exports are Admin-only and must still follow existing authorization policies.

Products and customers must preserve the official accounting codes.

### 3. Export is not a lifecycle state

Exporting an order must not set its status to `exported` or otherwise alter lifecycle state. Export actions should be read-only with respect to business records unless a separately approved requirement says otherwise.

### 4. Filename and format consistency

Define predictable filenames, worksheet names, headers, date/time formatting, number handling, Arabic/Unicode compatibility, and safe spreadsheet-cell handling.

Mitigate formula injection for user-controlled strings beginning with spreadsheet formula prefixes. Avoid lossy conversion of identifiers/codes, leading zeros, phone numbers, or large values.

### 5. Scale and delivery

Reuse the maintained Laravel-compatible Excel solution already present/approved. Prefer synchronous exports while data volume is safely bounded. Do not add Redis/queues/background infrastructure unless repository evidence proves it is necessary and owner approval is obtained.

## Scope Boundaries

Do not:

- change order states during export;
- create a reporting warehouse or generic speculative export framework;
- redesign business tables or R03 metrics;
- add scheduled/email exports;
- add import changes unrelated to export compatibility;
- expose exports publicly;
- add R05 final documentation/QA beyond R04 verification;
- change the accounting import template without an explicit approved specification.

## Gate A — Required Planning Deliverable

Return a structured plan containing:

1. **Prerequisite verification** with repository evidence.
2. **Existing export assessment** — package/version/config, export classes/actions, order export behavior, tests, and any `exported` status coupling that remains.
3. **Complete Admin table inventory matrix** — page/resource/table, data grain, filters/search, included/excluded decision, export modes, authorization, proposed columns, and rationale.
4. **Reusable design** — the smallest maintainable approach for shared behavior without premature abstraction; identify which exports genuinely need dedicated mapping.
5. **Column contracts** — ordered headers and source fields for each included table, including code/phone formatting, nullable size behavior, delivered/remaining quantities, dates/timezone, and price privacy.
6. **Filtered/selected-row behavior** — exact mechanics and limitations for each table type, including dashboard drill-downs.
7. **Security and spreadsheet-safety plan** — Admin authorization, hidden price handling, formula injection, escaping, Unicode, identifiers, and sensitive-field review.
8. **Performance plan** — expected dataset sizes, memory/query behavior, chunking where supported, and a clear threshold for any future queued export without implementing it now.
9. **Implementation file map** — exact files to create/modify.
10. **Test plan** — columns/order, row grain, filters, selected rows, lifecycle immutability, codes/leading zeros, optional sizes, partial deliveries, cancelled orders where applicable, authorization, Unicode, formula injection, and empty tables.
11. **Documentation delta, risks, rollback, verification commands, ordered execution steps, and blockers/questions.**

## Gate A Stop Condition

After submitting the plan, do not edit code, generate exports, mutate order statuses, commit, or begin R05. Wait for explicit approval and WRITE authorization.

## Gate B — Execution Contract (Only After Approval)

Implement only the approved R04 matrix and design. Preserve unrelated work.

At handoff, provide:

- export coverage matrix as implemented;
- changed files;
- sample filenames and column contracts;
- tests/checks and their results;
- confirmation that exporting does not mutate lifecycle status;
- security/formula-injection verification;
- any excluded table and reason;
- a clear stop awaiting R04 acceptance before R05.

## R04 Acceptance Criteria

R04 is complete only when:

- every approved Admin business-data table has an appropriate Excel export action;
- exclusions are intentional and documented;
- exports reflect the correct table scope/filters/selection behavior;
- accounting codes and order-item snapshot fields are preserved accurately;
- optional size and delivered/remaining quantities export correctly;
- no export changes an order lifecycle state;
- Admin authorization and spreadsheet-safety controls are effective;
- Unicode, identifiers, leading zeros, and large values are not corrupted;
- no unapproved queue/reporting architecture is introduced;
- required tests and checks pass.
