# REL-W02 Gate B1 production preparation

Draft for a future separately approved Hostinger VPS deployment. Nothing here has been installed on Hostinger. Replace example paths/domain and verify the actual VPS memory, database version, PHP extensions, TLS and private provider connectivity before use. Shared hosting must support a persistent worker and cron; otherwise it does not satisfy this design.

## Runtime and release layout

Repository baseline: Laravel 12.69.2, Filament 4.13.2, PHP 8.3.33, Vite 7.3.6. Use the committed Composer/npm lock files. Build assets with `npm ci` and `npm run build` on a build host supporting package.json's dependencies; production serves `public/build` and needs no Node daemon.

Proposed runtime: Ubuntu 24.04, Nginx, PHP 8.3 FPM and CLI, MariaDB 10.11, Supervisor, cron. The real target engine has not been inspected. PHP needs ctype, curl, DOM/XML, fileinfo, filter, GD, hash, iconv, intl, mbstring, openssl, pcre, PDO/pdo_mysql, session, tokenizer and zip. Enable OPcache for FPM and pcntl/posix for CLI worker timeout/signals. Run `composer check-platform-reqs --no-dev` against the installed runtime before activation; CLI and FPM can have different ini files. Local default CLI lacks GD; the same check passes with its existing GD DLL enabled explicitly.

Use `/var/www/wholesale-order-system/releases/<release-id>` and a `current` symlink. Shared `.env` and `storage/` live under `shared/`; link them into each release. Keep `bootstrap/cache` release-local and writable. Give www-data write access only to storage and bootstrap/cache, not application source. Preserve APP_KEY across releases. Public local uploads require `php artisan storage:link`; configured R2 credentials/bucket and public URL require a separate connectivity check. Back up local uploads and remote objects according to the active disk.

Environment checklist (values supplied securely, never committed): APP_ENV=production, APP_DEBUG=false, HTTPS APP_URL, database credentials, SESSION_DRIVER=database, SESSION_SECURE_COOKIE=true, CACHE_STORE=database, QUEUE_CONNECTION=database, DB_QUEUE_RETRY_AFTER=90. Database cache is required for shared scheduler/pacing locks. Keep WHATSAPP_TIMEOUT=10 below job timeout30 and retry_after90. Set WHATSAPP_DRIVER=waha only for the approved existing provider; blank falls back to WHATSAPP_PROVIDER. WAHA is the approved initial production transport; unknown drivers fail closed. Vrobo is excluded from this release and has no active release dependency. Keep WHATSAPP_ENABLED=false until credentials, owner number, webhook secret and provider connectivity are validated. Use a positive webhook timestamp tolerance (default300).

PHP-FPM starting point: `pm=ondemand`, `pm.max_children=4`, `pm.process_idle_timeout=10s`, `pm.max_requests=500`, memory_limit=256M, upload_max_filesize=5M, post_max_size=8M. This is an initial budget, not a measured capacity claim; size children from actual worker RSS and VPS RAM. Product images/imports are bounded at5120KB in application validation. Nginx draft intentionally serves only public/ and executes only index.php; TLS certificate/redirect configuration must be added and tested before public use.

## Delivery and interruption semantics

One Supervisor worker consumes `whatsapp-orders,whatsapp-campaigns` in that priority order. Dispatches explicitly use the database connection even if an accidental default queue setting differs. Notification snapshots are created inside the order/lifecycle transaction, with queue publishing only after commit. If publishing fails, the durable pending row remains; scheduler recovery republishes it. Duplicate queued jobs claim a row under a database lock and cannot send twice.

`SendWhatsAppDispatch` timeout30; `$tries=0` permits repeated releases for pacing, while `$maxExceptions=3` bounds uncaught infrastructure errors. Recipient-resolution exceptions have their own durable limit3 and backoff30/120 seconds. Releases for campaign pacing do not consume that resolution allowance. Send attempts are recorded before the external call. Any ambiguous send/empty receipt is `unknown` and never automatically resent. Worker failure after claim has the same result. Scheduler marks processing rows older5minutes unknown; this protects interrupted sends, but requires the scheduler to run.

Every30seconds the scheduler recovers up to100 pending order dispatches and examines at most100 due campaigns, selecting one eligible recipient overall. Drafts persist selected customers and deterministic template variations. Pause/consent/product/template status is checked again in the worker. Campaign scheduling and worker pacing use shared database cache with a30second gap; no foreach/sleep network loop. Continuously busy order queue may delay campaigns; priority is intentional. Paused jobs are consumed without sending; their durable dispatch remains pending and the scheduler republishes it after resume. Sent/unknown/skipped records are terminal. Safe Admin retry is limited to failed unsent records with no receipt and fewer than3 resolution failures. Unknown records require provider-side investigation; do not run blanket `queue:retry all` as a message-resend policy.

Laravel failed_jobs remains available through `php artisan queue:failed`; caught delivery failures are primarily in whatsapp_dispatches and campaign recipients, visible in Admin. Inspect both. Never expose raw provider responses, keys or personal message bodies in operational logs.

Install the example Supervisor and cron files only after deployment authorization. Validate `nginx -t`, Supervisor configuration and PHP socket path on the VPS. Restarting workers is required after release activation: `php artisan queue:restart`; Supervisor autorestarts them. `php artisan schedule:interrupt` ends the old sub-minute scheduler loop after activation. Keep the shared cache across this transition. Rotate scheduler/application logs and retain failed-job records until reviewed.

## Backup and restoration

Create a root-only `/etc/wholesale-backup.cnf` (mode0600), with `[client]` user/password/host for a least-privilege backup account. Supply secrets out of band. Below commands are examples for that prepared Linux host, not commands executed during Gate B1:

```bash
set -euo pipefail
umask 077
backup_dir=/srv/backups/wholesale
stamp=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p "$backup_dir"
mariadb-dump --defaults-extra-file=/etc/wholesale-backup.cnf --single-transaction --quick --routines --triggers --events wholesale_production | gzip > "$backup_dir/database-$stamp.sql.gz"
tar -C /var/www/wholesale-order-system/shared -czf "$backup_dir/storage-$stamp.tar.gz" storage
sha256sum "$backup_dir/database-$stamp.sql.gz" "$backup_dir/storage-$stamp.tar.gz" > "$backup_dir/checksums-$stamp.txt"
```

Do not run concurrent DDL during the dump. Encrypt/upload backups off-server, verify upload/checksums, then apply retention7daily/4weekly/3monthly copies. Back up APP_KEY and environment secrets in a separate encrypted access-controlled vault. R2 objects need versioning or a separately verified object backup; the local storage archive is not an R2 backup. Before migration, capture a fresh dump and release identifier.

Restore drill: create an isolated empty restore database, verify checksums, import with `gzip -dc <dump> | mariadb --defaults-extra-file=<restore-only-config> <isolated-database>`, extract storage into an isolated directory and boot the matching release with restored APP_KEY and provider disabled. Verify counts, order snapshots, hidden-price privacy, Admin auth and media. Never substitute production credentials for restore-only credentials. Record recovery duration and maximum data loss from backup cadence. A restore drill on the actual deployment storage is a release blocker until performed.

## Future approved activation sequence

1. Verify domain/TLS, server dependencies, exact engine/version, secured environment, database permissions and a successful isolated restore drill. Review local [database evidence](REL-W02-DATABASE-VERIFICATION.md) and historical rollback limits.
2. Build a new immutable release using lock files (`composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`). Composer scripts run package discovery/Filament upgrade; inspect results. Link shared environment/storage, prepare permissions, run platform checks. Do not use development `composer setup` or generate a new APP_KEY.
3. Pause campaign generation and stop/drain the worker before schema changes; use maintenance mode if required. Take the fresh backup. Run only the reviewed `php artisan migrate --force`; seed only `php artisan db:seed --class=WhatsAppOrderTemplatesSeeder --force` if needed. Never run migrate:fresh or broad demo seeders on production.
4. Run `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` in the new release with its production environment. Review their output. Atomically point current at the verified release, reload PHP-FPM, interrupt old scheduler and restart/start Supervisor worker. Resume cron/campaigns, leave maintenance mode and test /up, catalog, private Admin, cart/order, status/export and one approved owner/customer test notification.
5. Inspect dispatch statuses, oldest pending jobs, failed_jobs and cron logs. Watch worker process status and cache pacing; /up alone does not prove worker/provider health. Send no real customer campaign during validation without its approved recipients/content.

Rollback: stop outbound worker/cron first. Repoint current only if schema and queued payloads remain compatible. Otherwise use a reviewed forward fix or tested database/storage restoration; restoring a DB may lose orders after the backup and may resurrect previously sent pending jobs. Reconcile send receipts before restarting to avoid duplicates. No automatic destructive rollback script is supplied.

Gate B1 delivered drafts, not tested installed VPS services. Remaining activation dependencies: actual VPS access/runtime/domain, TLS, backup/restore evidence, provider credentials and separately approved deployment. No Vrobo contract or implementation is required. Follow the current [Gate B2 activation checklist](REL-W02-GATE-B2-HANDOFF.md) and [WAHA production recovery guide](REL-W02-WAHA-PRODUCTION.md).

Framework behavior was cross-checked against installed Artisan help/source and the official [Laravel12 queue](https://laravel.com/docs/12.x/queues) and [scheduler](https://laravel.com/docs/12.x/scheduling) documentation.
