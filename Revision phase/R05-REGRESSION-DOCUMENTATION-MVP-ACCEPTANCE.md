# R05 — Regression, Documentation & Revised MVP Acceptance

## Delegation Mode

You are responsible for **R05 — Regression, Documentation & Revised MVP Acceptance**, the closure phase for the client-review revisions.

The first response must be **PLAN ONLY**. Inspect the repository, accepted R01–R04 work, current tests, and all governing documents. Do not modify files or fix defects until the owner reviews and approves the plan and grants WRITE authorization.

## Prerequisite Gate

R01, R02, R03, and R04 must each be accepted. Verify that fact against repository behavior and available handoffs; do not assume completion from filenames alone.

If a prerequisite is materially missing, produce a blocker report identifying the owning phase. Do not silently implement missing features inside R05.

## Objective

Validate the revised MVP end to end, repair only approved defects found within the revision scope, reconcile all project documentation with the implemented truth, and produce an evidence-based release/acceptance handoff.

## Authoritative Revised MVP Behavior

The final system must reflect these approved rules:

- Orders use `new`, `confirmed`, `partially_delivered`, `delivered`, and `cancelled`.
- Export is an action, never an order lifecycle status.
- Customers may request any positive quantity without stock-based blocking.
- Exact available stock is Admin-only and absent from customer-facing props/source/messages.
- Operations can edit an order while it is `new`, then confirm or cancel it.
- Each order item tracks delivered quantity; remaining quantity is derived as ordered minus delivered.
- Delivery may be partial and repeated safely until the order becomes fully delivered.
- Current production requirements include remaining quantities from `confirmed` and `partially_delivered` orders only.
- `new`, `cancelled`, and `delivered` orders do not contribute to outstanding production requirements.
- Product size selection is optional per product, defaults off, and remains fully supported when enabled.
- Customer quantity selection offers `5`, `10`, and a custom positive integer.
- The Admin dashboard provides traceable operational KPIs and product requirements with reconcilable drill-downs.
- Meaningful Admin data tables provide approved Excel exports without mutating order status.
- The product remains an order-capture and operational tracking layer, not ERP, accounting, inventory transaction, shipping, or payment software.

## Required Documentation Reconciliation

Audit and update, only during approved Gate B execution:

- `README.md`
- `01-PRD.md`
- `02-IMPLEMENTATION-BLUEPRINT.md`
- `03-WORK-PACKAGES.md`
- `04-DEVELOPMENT-GUARDRAILS.md`
- `05-DATABASE-SCHEMA.md`
- `06-MVP-ACCEPTANCE.md`

Documentation must describe the implemented system precisely, remove obsolete claims, and keep explicit boundaries intact.

At minimum reconcile:

- customer stock visibility and the removal of stock-based blocking;
- optional size selection and product flag default;
- revised statuses and transition semantics;
- delivered/remaining quantity rules;
- pending-order editing and cancellation;
- Admin Order Management versus Delivered Orders history;
- dashboard KPI formulas and product drill-down;
- Excel export coverage and the removal of `exported` status semantics;
- revised end-to-end acceptance flow;
- the distinction between lightweight fulfillment tracking and prohibited shipping/inventory systems.

Do not rewrite history vaguely. Keep original phase records understandable while clearly adding the approved Revision Phase R01–R05.

## Required End-to-End Scenarios

Plan and execute coverage for at least:

1. **Unsized product:** Admin creates product with size disabled → customer sees colors but no size → selects 5/10/custom quantity → submits an amount above internal stock successfully → no stock value leaks.
2. **Sized product:** Admin enables size → valid color/size selection is required → order snapshot preserves size.
3. **Confirmation edit:** customer submits → Admin edits new-order item/quantity after agreement → totals/snapshots remain correct → confirms.
4. **Cancellation:** new order is cancelled → remains historical → excluded from operational KPIs and outstanding quantities.
5. **Partial delivery:** confirmed multi-item order receives partial quantities → status and remaining quantities are correct → second delivery completes it → status becomes delivered.
6. **Invalid delivery:** zero-only submission, negative value, over-delivery, invalid state, and repeat/concurrent submission are rejected safely.
7. **Dashboard reconciliation:** KPI → filtered source table and product card → drill-down rows → individual order all agree numerically.
8. **Delivered archive:** completed order appears in Delivered Orders and no longer contributes to current production needs.
9. **Excel:** exports from every approved table respect filters/selection, preserve codes and optional sizes, safely represent Arabic/user text, and never mutate status.
10. **Privacy/security regression:** hidden prices remain absent from public payloads/source and Admin-only stock remains private.

## Scope Boundaries

R05 may fix defects only when they are clearly regressions or acceptance blockers within approved R01–R04 behavior and are included in the approved Gate B plan.

Do not add new features, redesign accepted UX, introduce broad refactors, or add ERP/accounting/inventory/shipping/payment capabilities. Do not weaken tests to obtain a pass.

## Gate A — Required Planning Deliverable

Return a structured plan containing:

1. **Prerequisite evidence matrix** — each R01–R04 acceptance area mapped to implementation files/tests and current observed status.
2. **Requirements traceability matrix** — every revised business rule mapped to schema/domain/UI/test/documentation evidence.
3. **Current test/quality inventory** — test framework, suites, commands, static analysis, formatting, frontend build/type/lint checks, and environment constraints.
4. **End-to-end test plan** — fixtures, steps, expected results, automation versus manual coverage, and data cleanup strategy for all required scenarios.
5. **Dashboard/export reconciliation plan** — how totals, drill-down rows, filters, and generated workbooks will be independently compared.
6. **Privacy/security verification plan** — stock and hidden-price payload/source inspection, authorization, invalid transitions, mass-assignment/tampering, and export safety.
7. **Migration/legacy validation** — migrated statuses, legacy variants/sizes, defaults, constraints, and production-safe checks.
8. **Documentation change matrix** — document, section, obsolete statement, replacement truth, and owning revision phase.
9. **Defect handling protocol** — severity, owning phase, whether R05 may repair it, retest requirements, and stop/escalation conditions.
10. **Release readiness checklist** — migrations, environment/config, backup/rollback considerations, build assets, smoke test, and sign-off evidence.
11. **Implementation file map** for documentation/tests/approved fixes only.
12. **Ordered execution steps, verification commands, risks, rollback, assumptions, and blockers/questions.**

Do not report PASS during Gate A. Planning evidence may identify expected gaps, but final acceptance requires Gate B execution results.

## Gate A Stop Condition

After submitting the plan:

- make no edits or commits;
- do not change tests or documentation;
- do not fix discovered defects;
- do not declare the revised MVP accepted;
- wait for explicit approval and WRITE authorization.

## Gate B — Execution Contract (Only After Approval)

Execute the approved validation and documentation plan. For any newly discovered blocker that requires scope beyond the approved plan, stop and request a decision.

The final handoff must include:

- PASS / PASS WITH ACTIONS / FAIL verdict;
- acceptance matrix with evidence for every revised rule;
- commands run and exact results, including skipped checks and reasons;
- defects found, fixed, deferred, and owning phase;
- changed files and documentation sections;
- migration/deployment/rollback notes;
- manual test evidence and reconciliation results;
- explicit remaining risks and owner actions;
- confirmation that no unapproved feature or architectural scope was introduced.

## R05 Acceptance Criteria

The revised MVP is accepted only when:

- all approved R01–R04 behaviors work together end to end;
- business-critical automated tests and project quality checks pass;
- required manual flows pass with reproducible evidence;
- dashboard totals and exports reconcile with source records;
- stock and hidden-price privacy rules hold;
- legacy data/migrations are safe or have an explicit approved deployment action;
- all seven governing documents match implemented behavior and each other;
- no unresolved blocker remains in the revised core flow;
- the system remains within its lean operational-order boundary.
