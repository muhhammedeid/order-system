# REL-W02 Gate B2 Handoff

Status: BLOCKED

Local implementation and verification are complete; production readiness is blocked by external provisioning and live validation. The owner confirmed VPS provisioning is in progress, approved KVM4/Ubuntu24.04/SSH-key deploy user, and prohibited unapproved customer messaging. Domain/test numbers are not finalized. No Hostinger/DNS/provider deployment, live message, public exposure or production data mutation was performed. No temporary domain was introduced.

## Delivered

- Accepted B1 implementation preserved: S1 cross-customer checks, normalized identity, atomic notification snapshots, Database Queue, complete owner/customer messages, campaigns/recipients/variations/media/public URL, controlled scheduler, pause/resume/dedupe/unknown protections.
- Exactly 18 baseline Pint files mechanically formatted; no behavioral refactors. Full Pint now passes.
- Active provider decision updated: WAHA only, Laravel remains source of truth, WhatsAppGateway/WahaGateway boundary retained. Vrobo is excluded and no contract blocks this release. Historical B1 remaining action marked retired; unknown future drivers still fail closed.
- WAHA transport hardened: no redirects with API key, only2xx accepted, bounded HTTP/connect timeouts, no POST retries, sanitized exceptions. Replay validation fails closed if tolerance is nonpositive. Retained manual sends preserve unknown acceptance/missing receipts rather than claiming sent/failed; warning copy updated in Arabic/English. Automatic notifications/campaigns remain background-only and do not determine core order success.
- Separate production Compose: authenticated loopback API, explicit NOWEB, published version+digest pin, persistent session/media volumes, restart policy, QR printing off, Dashboard/Swagger off, warn JSON/rotated logs and explicit media lifetime. Invalid bare image tag corrected after actual registry verification. Quiet Compose validation passed; no container started.
- Prepared Nginx HTTP/TLS templates with unresolved final-domain marker only, PHP/MariaDB configuration examples, existing single-worker Supervisor/cron, encrypted off-server backup script/config/cron and controlled restore/recovery procedure.
- KVM2/KVM4 comparison grounded in official plan specs; KVM4 approved. Performance specialist supplied a measured acceptance plan, not a fabricated capacity result.

## Files changed

The final release inventory is recorded below. B2 purposes: formatting-only18baseline files; adapter/webhook/manual-send safeguards and targeted regressions; active provider docs; WAHA production configuration/recovery; Hostinger/HTTPS/runtime/backup drafts; database verification and this handoff. Existing B1 domain changes are included in the candidate and not dropped.

## Validation

Commands run from `D:/Mohamed/wholesale-order-system`; no production environment was implied.

| Check / exact command | Result |
| --- | --- |
| `C:/php83/php.exe vendor/bin/pint --test` | PASS, entire repository |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result` | PASS: 654 tests / 3139 assertions, 50.555 seconds / 118 MB |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/OrderCreationTest.php tests/Feature/CustomerMatchingTest.php tests/Feature/CustomerPhoneNormalizationTest.php tests/Feature/OrderDeliveryTest.php tests/Feature/OrderManagementPageTest.php tests/Feature/StockPrivacyTest.php tests/Feature/AdminExcelExportTest.php tests/Feature/ProductUploadValidationTest.php tests/Feature/WhatsApp tests/Unit/WhatsApp` | PASS: 409 tests / 1925 assertions, 31.631 seconds; includes complete WhatsApp suite and core privacy/lifecycle/export/upload regressions |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Unit/WhatsApp/WahaGatewayTest.php tests/Feature/WhatsApp/WhatsAppWebhookTest.php tests/Feature/WhatsApp/DriverSelectionTest.php` | PASS: 51 tests / 90 assertions after transport/replay hardening |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/WhatsApp/MessageSenderTest.php tests/Feature/WhatsApp/WhatsAppConversationPageTest.php tests/Feature/WhatsApp/OrderWhatsAppPanelTest.php` | PASS: 32 tests / 118 assertions after manual unknown-result changes |
| `C:/php83/php.exe vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml --testdox --log-junit storage/app/rel-b2-mysql-results.xml` | PASS: 7 tests / 69 assertions, 0 failures/errors, 239.386 seconds / 62 MB; isolated real MariaDB10.11.14, [detailed evidence](REL-W02-GATE-B2-DATABASE-VERIFICATION.md) |
| `npm ci` | PASS: 110 packages installed, 111 audited, 0 reported vulnerabilities; locks unchanged |
| `npm run build` | PASS: 659 modules, 5.24 seconds; Node24.14.0/npm11.9.0, Vite7.3.6 |
| `C:/php83/php.exe -d extension=gd C:/ProgramData/ComposerSetup/bin/composer.phar check-platform-reqs --no-dev` | PASS: 24/24 local non-dev requirements with GD enabled for this process; default local CLI still has GD disabled. Production CLI/FPM checks remain pending |
| `docker compose -f deploy/waha/docker-compose.production.yml config --quiet` with a process-local dummy hash | PASS, Docker CLI29.8.0; no secrets printed, no Docker daemon/container launch |
| `docker manifest inspect --verbose devlikeapro/waha:noweb-2026.9.1` plus official registry tag metadata | PASS: published tag/digest/amd64 verified; original `:2026.9.1` lookup FAIL (nonexistent), corrected |
| `wsl -- bash -n /mnt/d/Mohamed/wholesale-order-system/deploy/backup.sh` | PASS syntax only; WSL user-session warning did not change exit0. No backup executed |
| `git diff --check` | PASS |

The first new timeout test incorrectly expected Laravel's fake to record a request whose callback threw before recording; corrected it to count actual callback attempts, retaining the one-attempt security assertion. No product regression was suppressed. Docker daemon is unavailable locally; registry/schema validation does not establish live session readiness. The actual production no-dev install, CLI/FPM platform check, backup upload/restore and service tests are pending.

## Specialist reviews

- @minimal-change-engineer: scope bounded to local preparation and accepted B1 behavior; existing safety controls adequate, no segmentation/evasion architecture needed.
- @application-security-engineer: redirect, replay and ambiguous manual-send issues resolved; reviewed actual transport/Compose/backup diff, no new mandatory finding. Test-guard reviewed targeted tests: network fakes at actual transport boundary, real ORM/privacy/state assertions, no material weakening.
- @database-optimizer: reran guarded real-engine suite,7/69 PASS; stopped only its own WSL keepalive. Only fixed-name temporary verification DB modified.
- @performance-benchmarker: KVM4 starting plan endorsed, actual capacity still unmeasured; KVM2 requires equivalent constrained-hardware evidence.
- clean-code-guard/docs-guard applied to implementation/documentation. Configurations documented as drafts and verified against actual adapter/source and official WAHA/Docker/Restic references.

## Release integration

Starting feature HEAD `f45089929c0b07a9e6c74534bc648b553ea919c7`, staging `0629146`; feature contains staging and is 13 commits ahead. main and feature diverge 1/27 from base `51670630401bbe08b615cec7fa6f63dd9a138c7b`. Main-only commit `b695e3d7821e5abcfae0291bbef9c470836618e0` changes three Home texts.

All three main intentions are already preserved in the current translated Arabic storefront: `lang/ar/storefront.php` home.badge (“أسعار جملة — إنتاج مصنعنا”), home.heading (“تصفح المنتجات واطلب”), and home.steps.quantity (“حدد الكمية”/available colors). `Home.vue` uses those keys and retains later responsive/i18n work. Do not reintroduce the old hard-coded Home template or its outdated stock messaging.

Local candidate branch `codex/rel-w02-waha-rc`; tag proposal `rel-w02-waha-rc.1` remains unreleased. Candidate parent is f45089929c0b07a9e6c74534bc648b553ea919c7. The user explicitly authorized one complete Gate B1/B2 release-candidate commit on 2026-09-30. This supersedes the earlier automatic approval rejection. Exact resulting SHA and clean-tree evidence are reported in the commit handoff. Merge, tag, push and deployment remain unauthorized. No force push or production activation is authorized. The existing history is captured in [the release ledger](REL-W02-RELEASE-COMMITS.txt); no main-only intent may be dropped.

## Live evidence matrix

| Area | Required cases | Current state |
| --- | --- | --- |
| Hostinger revision/runtime | immutable release/no-dev install, exact OS/Nginx/PHP CLI/FPM+GD/MariaDB/Composer/Supervisor/cron/Docker versions | PENDING_EXTERNAL_INPUT; VPS provisioning |
| Provider | container start, private health, QR, WORKING, text/image, signed webhook, incoming message, actual SERVER/DEVICE/READ ACKs | PENDING_EXTERNAL_INPUT; no approved test numbers/live session |
| Recovery | container restart, full VPS reboot, session reconnect, volume persistence, no unknown resend | PENDING_EXTERNAL_INPUT |
| Database | actual VPS isolated fresh/upgrade/generated/CHECK/FK/concurrency verification then separate production provisioning | PENDING_EXTERNAL_INPUT; local7/69 PASS is not on-host proof |
| Worker | autostart/restart, queue:restart, reboot, failed/pending recovery, order priority | PENDING_EXTERNAL_INPUT; local regression PASS |
| Scheduler | continuous cron, scheduled gradual dispatch, stale claim recovery | PENDING_EXTERNAL_INPUT; local regression PASS |
| Domain/HTTPS | real DNS/TLS, redirect, renewal, secure cookies, current/public | PENDING_EXTERNAL_INPUT; domain not finalized |
| Backup | encrypted off-server DB/storage+appropriate media, real isolated restore, counts/snapshots/Admin/privacy/media and recovery duration | PENDING_EXTERNAL_INPUT; script syntax/source reviewed only |
| Customer smoke | home/catalog/sized/unsized/cart/checkout/customer reuse/order/success | PENDING_EXTERNAL_INPUT on real HTTPS; local tests/build PASS |
| Admin smoke | login/products/customers/orders/edit/confirm/cancel/partial/full delivery/production requirements/history/Excel | PENDING_EXTERNAL_INPUT on real HTTPS; local tests PASS |
| Privacy | public hidden-price/stock absence, private Admin data, template/message routing | local PASS; HTTPS/live recheck pending |
| Live automation | owner/customer/status/partial/full notification, manual retained operation, small approved campaign variations/media/URL/cadence/pause/resume/failure/unknown/unavailable WAHA | PENDING_EXTERNAL_INPUT; no customer messaging authorized |
| Resources | CPU/RAM/swap/disk/FPM/DB/queue lag/WAHA under representative load, backup and reconnect | UNMEASURED; [measurement gates](REL-W02-HOSTINGER-ACTIVATION.md) |
| Rollback | compatible code switch and actual isolated DB/storage restore, receipt reconciliation | PREPARED_NOT_TESTED on VPS |

## Scope confirmation

- No Vrobo integration/dependency, order/shipping/COD adaptation, Redis/control panel, evasion or speculative marketing architecture.
- Laravel remains the source of truth; WAHA remains transport only.
- Gate B2 local work only; no live deployment, customer messages or production data writes.
- WAHA is unofficial; account/session restriction/ban risk remains and requires owner acceptance before launch. Pacing/consent do not guarantee account safety.

## Remaining actions

0. Obtain separate integration approval before merging main or staging. The current authorization covers the release-candidate commit only; do not blindly cherry-pick the historical Home commit.
1. Receive provisioned VPS/key-based deploy access, final domain, approved test recipients and secure owner/provider/backup configuration. Do not send secrets in chat or repository.
2. Obtain separately authorized activation after handoff review, then execute the prepared [Hostinger plan](REL-W02-HOSTINGER-ACTIVATION.md), [WAHA live/recovery guide](REL-W02-WAHA-PRODUCTION.md) and existing release runbook.
3. Collect every live matrix result, including real encrypted restore, HTTPS, reboot recovery and resource headroom, before declaring PRODUCTION_READY. No Vrobo contract is an outstanding action.

## Exact candidate file inventory

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
- `app/Http/Controllers/CartController.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/WhatsAppWebhookController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
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
- `app/Support/Imports/CustomersImporter.php`
- `app/Support/Imports/HeaderContractException.php`
- `app/Support/Imports/ImportResult.php`
- `app/Support/Imports/RawSheetReader.php`
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
- `bootstrap/providers.php`
- `config/queue.php`
- `config/whatsapp.php`
- `database/factories/ProductFactory.php`
- `database/migrations/2026_09_17_164232_add_product_variant_id_to_order_items_table.php`
- `database/migrations/2026_09_20_190000_normalize_product_choice_flags.php`
- `database/migrations/2026_09_20_200000_add_image_path_to_categories_table.php`
- `database/migrations/2026_09_20_200100_expand_order_item_variant_snapshots.php`
- `database/migrations/2026_09_22_130000_add_order_id_to_whatsapp_messages_table.php`
- `database/migrations/2026_09_29_090000_add_normalized_customer_phones.php`
- `database/migrations/2026_09_29_100000_create_whatsapp_dispatches_table.php`
- `database/migrations/2026_09_29_110000_create_whatsapp_campaigns_table.php`
- `database/seeders/WhatsAppOrderTemplatesSeeder.php`
- `deploy/backup.cron.example`
- `deploy/backup.env.example`
- `deploy/backup.sh`
- `deploy/mariadb.cnf.example`
- `deploy/nginx.conf.example`
- `deploy/nginx.tls.conf.example`
- `deploy/php-production.ini.example`
- `deploy/scheduler.cron.example`
- `deploy/supervisor.conf.example`
- `deploy/waha/docker-compose.production.yml`
- `deploy/waha/docker-compose.yml`
- `docs/10-WHATSAPP-INTEGRATION.md`
- `docs/REL-W02-DATABASE-VERIFICATION.md`
- `docs/REL-W02-GATE-B1-HANDOFF.md`
- `docs/REL-W02-GATE-B2-DATABASE-VERIFICATION.md`
- `docs/REL-W02-GATE-B2-HANDOFF.md`
- `docs/REL-W02-HOSTINGER-ACTIVATION.md`
- `docs/REL-W02-PRODUCTION-RUNBOOK.md`
- `docs/REL-W02-WAHA-PRODUCTION.md`
- `lang/ar/admin.php`
- `lang/ar/campaigns.php`
- `lang/ar/dispatches.php`
- `lang/en/admin.php`
- `lang/en/campaigns.php`
- `lang/en/dispatches.php`
- `phpunit.mysql.xml`
- `routes/console.php`
- `tests/Feature/CatalogTest.php`
- `tests/Feature/CustomerImportTest.php`
- `tests/Feature/CustomerMatchingTest.php`
- `tests/Feature/CustomerPhoneNormalizationTest.php`
- `tests/Feature/CustomersTest.php`
- `tests/Feature/ProductUploadValidationTest.php`
- `tests/Feature/ProductVariantsTest.php`
- `tests/Feature/VariantErrorHandlingTest.php`
- `tests/Feature/VariantManagementPageTest.php`
- `tests/Feature/WhatsApp/DispatchAdminTest.php`
- `tests/Feature/WhatsApp/DriverSelectionTest.php`
- `tests/Feature/WhatsApp/MessageSenderTest.php`
- `tests/Feature/WhatsApp/OrderConversationsTest.php`
- `tests/Feature/WhatsApp/OrderStatusNotificationTest.php`
- `tests/Feature/WhatsApp/QueuedDispatchTest.php`
- `tests/Feature/WhatsApp/TemplatePrivacyContextTest.php`
- `tests/Feature/WhatsApp/WhatsAppCampaignTest.php`
- `tests/Feature/WhatsApp/WhatsAppConversationPageTest.php`
- `tests/Feature/WhatsApp/WhatsAppWebhookTest.php`
- `tests/Production/concurrent-worker.php`
- `tests/Production/DatabaseGuard.php`
- `tests/Production/ProductionDatabaseTest.php`
- `tests/Unit/WhatsApp/SentMessageTest.php`
- `tests/Unit/WhatsApp/WahaGatewayTest.php`
- `docs/REL-W02-RELEASE-COMMITS.txt`
