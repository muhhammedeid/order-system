# REL-W02 Gate B1 Handoff

Status: PASS_WITH_ACTIONS

Historical Gate B1 record, subsequently accepted by the owner. Gate B2 supersedes its remaining actions: the18baseline Pint violations are now resolved; WAHA is approved for initial production and the former Vrobo contract action is retired. See [current Gate B2 status](REL-W02-GATE-B2-HANDOFF.md) for live activation evidence and remaining external inputs.

Gate B1 provider-neutral implementation is complete. This is not approval to deploy or a claim that the entire MVP is production-ready. Branch remains `feature/p08-whatsapp-module`, HEAD `f45089929c0b07a9e6c74534bc648b553ea919c7`; no commit/push/merge/deployment was performed. Initial working tree was clean; interruption recovery preserved all in-progress changes and reviewed the final diff.

## Delivered

- S1 cross-customer routing → both first-contact resolution and sending fail closed when conversation ownership differs from the order/customer; worker rechecks under lock before claiming. Owner messages require an unlinked owner conversation. Regression coverage checks no send/no message mutation for mismatch.
- Egyptian identity → canonical `201[0125]xxxxxxxx` for supported national/international/+20/0020/formatted/Arabic/Persian-digit numbers. Original phone/accounting codes remain intact; unrecognized legacy values remain unchanged. Indexed nullable normalized columns are nonunique; earliest existing duplicate is matched deterministically. A hashed database identity lock serializes equivalent-phone customer creation. Public checkout cannot replace an existing customer's WhatsApp/accounting code. Inbox ambiguity remains fail closed.
- Provider boundary → selectable WHATSAPP_DRIVER with legacy WHATSAPP_PROVIDER fallback, existing WAHA adapter and unavailable adapter for future/unknown drivers. SentMessage is provider-neutral; WAHA response parsing lives inside its adapter factory. New notifications/jobs/campaigns depend on WhatsAppGateway; no Vrobo calls or guessed webhook/ACK/idempotency contracts exist. Existing WAHA inbound infrastructure remains explicitly provider-specific and driver guarded.
- Background notifications → database dispatch snapshots inside business transactions, after-commit database queue publishing, durable pending recovery, stable dedupe keys, locked claims and priority order queue. Provider I/O occurs only in the worker for these automatic notifications. Manual Inbox sending remains a separate existing synchronous Admin operation.
- Owner contract → order number, customer name/phone, total quantity and authenticated Admin URL. Existing edited owner templates receive missing mandatory fields at render time; no customer's message receives owner-only variables. Failures/send state are persisted and visible in read-only Admin tracking.
- Campaigns → product, selected customers and deterministic selected template variations; draft/start/schedule/pause/resume; recipient and dispatch state, attempts, sent_at, failure reason/provider receipt, image URL/MIME and public product URL snapshots. Campaign/customer unique constraint and dispatch dedupe prevent repeat sends. Consent and active product/template are revalidated. Scheduler and worker pacing both enforce 30 seconds through shared cache. Failed unsent retry is bounded; unknown sends cannot retry automatically.
- Template categories → order_update and product_announcement plus retained legacy categories; product_url and isolated owner variables; fail-closed unknown/missing variables, oversized expansion and private fields. Existing historical item snapshots drive order text.
- Interruption safety → a claimed send that times out, lacks a receipt or loses its worker becomes unknown; stale processing recovery does not resend it. Failed queue publication retains pending state. Tests exercise rollback/no precommit publication, publish failure recovery, stale processing and duplicate jobs.
- S2 readiness fixes → signed body/header timestamp and configured webhook session validation; product/category raster MIME allowlist and 5120 KB limit, private bounded spreadsheet uploads; existing owner template completion, Arabic number eligibility, AVIF MIME and4096-character expanded template cap. Repaired historical variant-link empty down() and message foreign-key/index down() order found by real-engine verification.
- Production preparation → [runtime/release/queue/backup/deployment runbook](REL-W02-PRODUCTION-RUNBOOK.md), draft Nginx/Supervisor/cron. No server configuration was installed.

## Validation

Commands run from the repository root. PHPUnit invoked directly with the same repository phpunit.xml because the local artisan-test subprocess stalled; no tests were weakened or skipped to obtain success.

| Command | Final result |
| --- | --- |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result` | PASS: 650 tests, 3123 assertions; 76.529 seconds, 118 MB. Subsequent localized status-label change passed campaign subset below |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/OrderCreationTest.php tests/Feature/CustomerMatchingTest.php tests/Feature/CustomerPhoneNormalizationTest.php tests/Feature/OrderDeliveryTest.php tests/Feature/OrderManagementPageTest.php tests/Feature/StockPrivacyTest.php tests/Feature/AdminExcelExportTest.php tests/Feature/ProductUploadValidationTest.php tests/Feature/WhatsApp tests/Unit/WhatsApp` | PASS: 404 tests / 1902 assertions before the final category-upload addition; final upload subset below and full suite cover that addition |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/ProductUploadValidationTest.php` | PASS: 3 tests / 21 assertions: product/category SVG and oversized raster rejected before persistence/storage |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml --testdox --log-junit storage/app/rel-b1-mysql-results.xml` | PASS: 7 tests, 69 assertions, 0 failures/errors; 168.426 seconds, 62 MB |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/WhatsApp/WhatsAppCampaignTest.php` | PASS: 14 tests, 79 assertions after final Arabic status-label correction |
| `npm run build` | PASS: 659 modules; final build 7.55 seconds, production assets built; initial sandbox esbuild EPERM resolved by authorized rerun |
| `C:/php83/php.exe vendor/bin/pint --test` | FAIL: 18 pre-existing unchanged files, listed below |
| Pint `--test` with every changed/new PHP path from `git diff --name-only -- '*.php'` and `git ls-files --others --exclude-standard -- '*.php'` | PASS all changed/new PHP, including production harness |
| `git diff --check` | PASS |
| `C:/php83/php.exe C:/ProgramData/ComposerSetup/bin/composer.phar check-platform-reqs --no-dev` | FAIL default local CLI: GD disabled;23 other requirements pass |
| Same Composer command with PHP `-d extension=gd` | PASS: 24/24 requirements; existing DLL enabled for that process only |

Earlier runs exposed a wrong campaign-test attribute assertion and the upload test's incorrect FileUpload state shape; these test-harness errors were corrected, not suppressed. Real MariaDB exposed an actual migration rollback defect which was repaired and reverified. [Detailed database execution and safety evidence](REL-W02-DATABASE-VERIFICATION.md) records harness/environment failures and final results.

Full Pint's unchanged baseline violations: app/Http/Controllers/CartController.php; app/Http/Middleware/HandleInertiaRequests.php; app/Support/Imports/CustomersImporter.php; HeaderContractException.php; ImportResult.php; RawSheetReader.php; bootstrap/providers.php; database/factories/ProductFactory.php; database/migrations/2026_09_20_190000_normalize_product_choice_flags.php;2026_09_20_200000_add_image_path_to_categories_table.php;2026_09_20_200100_expand_order_item_variant_snapshots.php; tests/Feature/CatalogTest.php; CustomerImportTest.php; CustomerMatchingTest.php; CustomersTest.php; ProductVariantsTest.php; VariantErrorHandlingTest.php; VariantManagementPageTest.php. These files were not mechanically reformatted as unrelated scope.

## Security/privacy evidence

The full/targeted suites cover hidden request-price and stock in storefront/cart/checkout errors, export blanking, server revalidation, immutable snapshots, no stock decrement, guest Admin restrictions, lifecycle tampering/stale delivery, one export row per variant and Excel Formula Injection. WhatsApp suites cover conversation ownership, customer/owner template isolation, price/stock/admin-note exclusion, ambiguous inbound identity, webhook signatures/replay/session, sanitized exceptions, driver unavailability, consent, dedupe and unknown-send recovery. Actual Livewire negative upload tests verify rejected files do not persist. No real credentials, message bodies or provider responses were added to logs/documentation; queued job payload is a dispatch ID. Dispatch message snapshots necessarily contain operational personal data and require normal private Admin/DB/backup access control.

## MySQL/MariaDB verification

Temporary database only: `wholesale_rel_b1_verify_20260930`, guarded against any other database. MariaDB10.11.14/Ubuntu24.04 WSL, utf8mb4_unicode_ci, strict SQL/InnoDB. No development/staging/production records were altered. Actual Hostinger engine remains unverified.

The seven tests cover fresh migrations/generated columns/NULL optional dimensions/unique and CHECK/FK constraints, full historical reset/upgrade with representative duplicates/Arabic snapshots/partial delivery, rollback/reapply of all three B1 migrations, two-process equivalent-phone creation, stale delivery, competing confirmation, duplicate order-number constraint and actual concurrent controller order creation with complete snapshots. Historical lossy rollbacks are documented; do not assume database rollback is lossless.

## Specialist reviews

- @minimal-change-engineer: bounded initial plan accepted; no speculative services/architecture added.
- @application-security-engineer and @code-reviewer: actual diff reviewed; cross-customer and privacy defenses accepted. Four S2 findings (old owner template completion, Arabic eligibility, AVIF MIME, expanded length) fixed with regressions. Final bounded review accepted dispatch Admin retry, migration down order and category/upload changes; no additional mandatory issue remained.
- @database-optimizer: isolated real MariaDB harness and concurrency evidence; actual migration defect escalated to primary integration and repaired.
- @internationalization-engineer: scoped Arabic review found raw English recipient statuses; resolved by reusing localized dispatch status labels, including the no-resend warning for unknown outcomes. No other mandatory Arabic/RTL issue found; no browser visual audit performed. No storefront redesign occurred.
- clean-code-guard/test-guard/docs-guard applied. Documentation correction accepted: paused jobs are consumed without sending, then pending dispatches are republished by scheduler after resume.

## Scope confirmation

- Gate B1 only; no Gate B2 or next package started.
- No client auth, ERP/accounting/payments/shipping, stock movements, Redis, analytics/segmentation, speculative provider services or unsupported Vrobo endpoints added.
- No commit, push, merge, production data writes or deployment.
- Supervisor/cron/Nginx and backup/restore procedures are preparation drafts; service operation and restore drills on Hostinger remain untested.

## Remaining actions

1. Resolve the 18 existing Pint baseline formatting violations and enable GD in the intended CLI/FPM runtime; rerun full Pint/platform checks before release acceptance.
2. Retired by the owner's final provider decision: Vrobo is excluded from initial production. WAHA is the approved transport; no Vrobo contract or implementation blocks release.
3. Verify actual Hostinger access/domain/TLS/runtime/database engine, private provider connectivity and a successful isolated backup restore. Install/validate the prepared worker and cron only after deployment approval; database cache is required.
4. Review the known branch divergence and select a release integration path before any separately authorized merge. Current feature contains staging; main has a separate cosmetic commit. No integration mutation was made.
5. Production launch remains blocked by target environment/restore/provider verification and deployment authorization. Stop at Gate B1.

## Files changed

Exact working-tree inventory (80 files) follows. Functional purposes: gateway/phone/privacy boundary; atomic notification queue/recovery and Admin tracking; lean campaign scheduler/Admin; safe uploads/webhooks; three new migrations and two historical rollback repairs; Arabic/English copy; regression/real-engine tests; deployment drafts/evidence.

- `.env.example`
- `app/Console/Commands/DispatchWhatsAppCampaigns.php`
- `app/Contracts/WhatsAppGateway.php`
- `app/Enums/WhatsAppMessageStatus.php`
- `app/Enums/WhatsAppTemplateType.php`
- `app/Filament/Concerns/HasImportAction.php`
- `app/Filament/Resources/Categories/Schemas/CategoryForm.php`
- `app/Filament/Resources/OrderManagement/OrderStatusActions.php`
- `app/Filament/Resources/Products/Schemas/ProductForm.php`
- `app/Filament/Resources/WhatsAppCampaigns/Pages/CreateWhatsAppCampaign.php`
- `app/Filament/Resources/WhatsAppCampaigns/Pages/EditWhatsAppCampaign.php`
- `app/Filament/Resources/WhatsAppCampaigns/Pages/ListWhatsAppCampaigns.php`
- `app/Filament/Resources/WhatsAppCampaigns/Pages/ViewWhatsAppCampaign.php`
- `app/Filament/Resources/WhatsAppCampaigns/RelationManagers/RecipientsRelationManager.php`
- `app/Filament/Resources/WhatsAppCampaigns/WhatsAppCampaignResource.php`
- `app/Filament/Resources/WhatsAppDispatches/Pages/ListWhatsAppDispatches.php`
- `app/Filament/Resources/WhatsAppDispatches/WhatsAppDispatchResource.php`
- `app/Filament/Resources/WhatsAppTemplates/Schemas/WhatsAppTemplateForm.php`
- `app/Filament/Resources/WhatsAppTemplates/Tables/WhatsAppTemplatesTable.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/WhatsAppWebhookController.php`
- `app/Http/Middleware/VerifyWhatsAppWebhookSignature.php`
- `app/Jobs/SendWhatsAppDispatch.php`
- `app/Models/Customer.php`
- `app/Models/WhatsAppCampaign.php`
- `app/Models/WhatsAppCampaignRecipient.php`
- `app/Models/WhatsAppDispatch.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/WhatsApp/UnavailableGateway.php`
- `app/Services/WhatsApp/WahaGateway.php`
- `app/Services/WhatsApp/WahaSentMessage.php`
- `app/Support/WhatsApp/Inbox/CustomerMatcher.php`
- `app/Support/WhatsApp/Order/OrderNotificationResult.php`
- `app/Support/WhatsApp/Order/OrderStatusNotifier.php`
- `app/Support/WhatsApp/Outbound/DispatchQueue.php`
- `app/Support/WhatsApp/Outbound/MessageSender.php`
- `app/Support/WhatsApp/Outbound/OrderConversations.php`
- `app/Support/WhatsApp/PhoneNumber.php`
- `app/Support/WhatsApp/SentMessage.php`
- `app/Support/WhatsApp/Templates/WhatsAppTemplateRenderer.php`
- `app/Support/WhatsApp/Templates/WhatsAppTemplateVariables.php`
- `config/queue.php`
- `config/whatsapp.php`
- `database/migrations/2026_09_17_164232_add_product_variant_id_to_order_items_table.php`
- `database/migrations/2026_09_22_130000_add_order_id_to_whatsapp_messages_table.php`
- `database/migrations/2026_09_29_090000_add_normalized_customer_phones.php`
- `database/migrations/2026_09_29_100000_create_whatsapp_dispatches_table.php`
- `database/migrations/2026_09_29_110000_create_whatsapp_campaigns_table.php`
- `database/seeders/WhatsAppOrderTemplatesSeeder.php`
- `deploy/nginx.conf.example`
- `deploy/scheduler.cron.example`
- `deploy/supervisor.conf.example`
- `docs/10-WHATSAPP-INTEGRATION.md`
- `docs/REL-W02-DATABASE-VERIFICATION.md`
- `docs/REL-W02-GATE-B1-HANDOFF.md`
- `docs/REL-W02-PRODUCTION-RUNBOOK.md`
- `lang/ar/admin.php`
- `lang/ar/campaigns.php`
- `lang/ar/dispatches.php`
- `lang/en/admin.php`
- `lang/en/campaigns.php`
- `lang/en/dispatches.php`
- `phpunit.mysql.xml`
- `routes/console.php`
- `tests/Feature/CustomerPhoneNormalizationTest.php`
- `tests/Feature/ProductUploadValidationTest.php`
- `tests/Feature/WhatsApp/DispatchAdminTest.php`
- `tests/Feature/WhatsApp/DriverSelectionTest.php`
- `tests/Feature/WhatsApp/MessageSenderTest.php`
- `tests/Feature/WhatsApp/OrderConversationsTest.php`
- `tests/Feature/WhatsApp/OrderStatusNotificationTest.php`
- `tests/Feature/WhatsApp/QueuedDispatchTest.php`
- `tests/Feature/WhatsApp/TemplatePrivacyContextTest.php`
- `tests/Feature/WhatsApp/WhatsAppCampaignTest.php`
- `tests/Feature/WhatsApp/WhatsAppWebhookTest.php`
- `tests/Production/concurrent-worker.php`
- `tests/Production/DatabaseGuard.php`
- `tests/Production/ProductionDatabaseTest.php`
- `tests/Unit/WhatsApp/SentMessageTest.php`
- `tests/Unit/WhatsApp/WahaGatewayTest.php`
