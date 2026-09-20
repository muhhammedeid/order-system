# Wholesale Order System — Agent Operating Contract

This file governs the primary OpenCode execution agent and every delegated subagent in this repository.

## 1. Mission

Deliver the approved Wholesale Order System MVP one work package at a time, with the smallest maintainable change that satisfies the written acceptance criteria.

The system is an operational wholesale order-capture layer for a shoe factory. It is not an ERP, accounting system, payment platform, warehouse system, CRM, shipping platform, or customer portal.

## 2. Authoritative Documents

Before changing code, identify the active work package and read only the documents needed for it. Resolve conflicts in this order:

1. The user's current explicit instruction.
2. `04-DEVELOPMENT-GUARDRAILS.md`.
3. The active package in `03-WORK-PACKAGES.md`.
4. `02-IMPLEMENTATION-BLUEPRINT.md`.
5. `01-PRD.md`.
6. `05-DATABASE-SCHEMA.md`.
7. `06-MVP-ACCEPTANCE.md`.
8. `README.md`.

If a request conflicts with a higher-authority document or crosses a stop condition, stop and report the exact conflict. Do not invent a workaround or silently expand scope.

## 3. Approved Architecture

- Laravel monolith for backend, routing, validation, persistence, and application logic.
- Vue 3 customer storefront through Inertia.js.
- Tailwind CSS and Vite.
- Filament Admin Panel; do not rebuild Admin in Vue.
- MySQL.
- Session-based cart; no cart table.
- Arabic-first, RTL, mobile-first storefront.
- English code, class, schema, and development names.
- Framework-native Laravel features before custom abstractions.

Do not introduce microservices, a separate SPA/API, DDD, CQRS, event sourcing, speculative repository/service layers, Redis, queues, workflow engines, client authentication, payments, invoices, stock movements, shipping, or advanced analytics unless a later explicitly approved package requires them.

## 4. Non-Negotiable Business Rules

- `product_code` and `customer_code` remain aligned with the accounting system.
- Match checkout customers by mobile number; do not create customer login accounts.
- `request_price` values must never appear in Inertia props, HTML, public JSON, logs intended for clients, or client-side JavaScript.
- Revalidate active products, variants, and quantities on the server before order creation.
- Create orders and item snapshots inside a database transaction.
- Preserve order-item snapshots when product data changes.
- Do not decrement `available_quantity` until the user makes that separate business decision.
- One exported row represents one ordered variant.

## 5. Primary Executor Ownership

The primary execution agent owns scope, integration, final edits, validation, and the handoff. Subagents advise or perform explicitly bounded tasks; they do not redefine scope or approve their own work.

For every non-trivial change:

1. Inspect repository status and existing implementation. Preserve unrelated user changes.
2. State the active work package, exact acceptance criteria, allowed files, and exclusions.
3. Build a short implementation plan tied to those criteria.
4. Invoke only the specialist agents justified by the routing table below.
5. Implement the smallest coherent change. Do not start the next work package.
6. Run targeted checks first, then the relevant broader suite.
7. Review the resulting diff and validate acceptance criteria with evidence.
8. Report changed files, commands run, results, remaining risks, and the package status.

Do not commit, push, deploy, modify production data, or rewrite user changes unless explicitly requested.

## 6. Agency-Agents Routing

Agency agents are OpenCode subagents installed under `.opencode/agents/` and invoked with `@<slug>`. Verify the local filename before first use; if a listed agent is unavailable, continue with the primary agent and report the missing optional reviewer rather than installing unrelated agents.

| Agent | Invoke when | Expected output | Access pattern |
|---|---|---|---|
| `@minimal-change-engineer` | Any medium/large implementation, refactor, or bug fix | Minimal-diff plan, affected files, regression risks, and explicit non-goals | Read-only advisory before implementation |
| `@frontend-developer` | Vue, Inertia, Tailwind, storefront interaction, responsive UI, or Core Web Vitals work | Vue-specific implementation/review aligned with Arabic-first mobile UX | Bounded frontend files only if delegated |
| `@filament-optimization-specialist` | Filament resources, forms, tables, actions, imports, exports, or Admin UX | Filament-version-aware recommendation or bounded implementation | Bounded Admin files only if delegated |
| `@internationalization-engineer` | Arabic copy, RTL/bidi behavior, locale formatting, or Phase P06-W01 | RTL/i18n audit with concrete failures and fixes | Prefer read-only review; bounded locale/UI edits when assigned |
| `@database-optimizer` | Migrations, indexes, relationship loading, query-count issues, or measured slow SQL | Schema/query review grounded in the approved lean schema | Read-only unless migration ownership is explicit |
| `@application-security-engineer` | Checkout/order creation, validation, sessions, uploads/imports, auth, hidden prices, or data exposure | Threat-focused review with file/line evidence and severity | Read-only review; never perform offensive testing |
| `@code-reviewer` | Every completed medium/large code change before handoff | Correctness, maintainability, regression, and scope findings ordered by severity | Read-only, review the actual diff |
| `@test-results-analyzer` | Tests fail, results are ambiguous, or a package has a substantial suite | Root-cause analysis separating product defects, test defects, and environment failures | Read-only analysis of code and outputs |
| `@test-automation-engineer` | Critical browser flow automation or Phase P06-W02 | Deterministic E2E coverage for the approved MVP journey | Test files only; no product redesign |
| `@performance-benchmarker` | A measured performance regression, explicit performance task, or final MVP readiness check | Reproducible baseline, bottleneck evidence, and proportional recommendations | Read-only measurement unless a fix is separately assigned |

### Routing rules

- Do not invoke an agent merely because it exists.
- Use at most two specialist subagents concurrently, and only for independent, preferably read-only tasks.
- Never allow overlapping writes. Assign exact file ownership when a subagent may edit.
- Do not delegate final integration, acceptance, or scope decisions.
- Reuse a specialist's result; do not ask multiple agents to repeat the same review.
- For a trivial, localized change, skip delegation and validate directly.
- For frontend plus backend work, complete the shared contract first, then delegate independent sides if their file ownership does not overlap.

## 7. Agents Excluded from the Default Workflow

Do not use the following unless the user explicitly asks and the task genuinely fits:

- `@senior-developer`: its default Livewire/FluxUI/Three.js and mandatory premium-animation guidance conflicts with this project's Vue/Inertia stack and lean scope.
- `@backend-architect` or `@software-architect`: their default API, microservice, DDD, and scalability focus can violate the approved monolith and no-speculative-architecture rules.
- `@rapid-prototyper`: do not trade approved schema, validation, or tests for demo speed.
- `@reality-checker`: its fixed screenshot scripts, premium-design checks, and default-fail rubric are not the project's acceptance contract. Use the actual package criteria instead.
- DevOps, SRE, penetration-testing, payment, AI, analytics, and mobile agents: outside the current MVP unless separately approved.

## 8. Implementation Standards

### Laravel

- Use Form Requests for non-trivial request validation.
- Use Eloquent relationships and eager loading where the access pattern demonstrates a need.
- Use database constraints for approved invariants such as unique product codes, unique variant combinations, and non-negative quantities where supported.
- Use transactions for order creation and other multi-write consistency boundaries.
- Avoid service classes, repositories, events, jobs, and observers unless they remove demonstrated complexity in the active package.
- Never trust cart price, availability, product status, or totals sent by the client.

### Vue / Inertia

- Send the minimum props necessary for the page.
- Never serialize hidden product prices.
- Keep components local until genuine reuse exists.
- Preserve server-side validation as the authority; client validation is for UX only.
- Use semantic HTML, keyboard-accessible controls, visible focus states, responsive layouts, and correct RTL behavior.
- Do not add PWA/offline mode, elaborate animation systems, or a new state-management library without approved need.

### Filament

- Inspect the installed Filament version before using APIs.
- Keep operational forms and tables direct and fast.
- Prevent N+1 queries in lists and relation displays.
- Keep destructive or status-changing actions explicit and confirmable where appropriate.
- Do not reproduce storefront screens or introduce roles beyond the one Admin role approved for MVP.

### Imports and exports

- Validate each import row and report invalid rows; never silently skip or duplicate them.
- Match products/customers using the approved external codes.
- Treat uploaded spreadsheet content as untrusted.
- Preserve the exact accounting-template requirement as an open dependency until the real template is supplied.

## 9. Validation and Quality Gates

Discover the repository's actual commands from `composer.json`, `package.json`, and CI configuration. Do not invent scripts.

Minimum evidence, where applicable:

- Targeted Laravel tests for changed business behavior.
- Required business-critical tests listed in `02-IMPLEMENTATION-BLUEPRINT.md`.
- Frontend lint/type checks and production build if configured.
- PHP formatting/static analysis if configured.
- Migration test on a clean test database for schema changes.
- Manual or automated proof that hidden prices are absent from public responses and Inertia props.
- Query-count or timing evidence before claiming a performance improvement.
- For P06-W02, verify the full flow: Catalog → Product → Cart → Customer → Order → Admin → Excel Export.

A package may be marked `PASS` only when every acceptance criterion has evidence and relevant checks pass. Otherwise use `PASS_WITH_ACTIONS`, `BLOCKED`, or `FAIL`, listing exact unresolved items. Do not call the MVP production-ready merely because one package passes.

## 10. Required Handoff Format

Use this compact structure:

```markdown
# <work-package> Handoff

Status: PASS | PASS_WITH_ACTIONS | BLOCKED | FAIL

## Delivered
- Requirement → implementation evidence

## Files changed
- `path`: purpose

## Validation
- `command`: PASS/FAIL and relevant result

## Specialist reviews
- `@agent`: findings accepted, resolved, or deferred with reason

## Scope confirmation
- No next package started
- No unapproved architecture/features added

## Remaining actions
- None, or an exact actionable list
```

Never hide failed checks, untested behavior, assumptions, or environment blockers.
