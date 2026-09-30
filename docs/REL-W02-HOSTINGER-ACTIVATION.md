# REL-W02 Hostinger activation preparation

Not executed. The owner approved KVM4 (4vCPU/16GB/200GB NVMe), clean Ubuntu24.04 LTS, SSH keys with a dedicated sudo deploy user, and public SSH/HTTP/HTTPS only. Provisioning, domain, approved WhatsApp test recipients and separately authorized deployment are pending. Never use a substitute domain or real customer audience.

## Runtime installation after authorization

Confirm `/etc/os-release` is Ubuntu24.04 and architecture matches the pinned WAHA image (linux/amd64). Install Nginx, PHP8.3 CLI/FPM, MariaDB10.11, Composer2, Supervisor, cron, Docker Engine and encrypted backup tooling. No Redis/control panel/separate frontend server is required.

Proposed Ubuntu package command, to be reviewed on the actual host:

```bash
sudo apt-get update
sudo apt-get install nginx php8.3-cli php8.3-fpm php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip php8.3-bcmath php8.3-opcache mariadb-server composer supervisor cron ufw git unzip curl ca-certificates restic
```

Install Docker Engine/Compose from its [official Ubuntu repository procedure](https://docs.docker.com/engine/install/ubuntu/), avoiding mixed Ubuntu docker.io packages. Inspect available package versions and record actual installed versions; the command above is not a promise of exact patch versions. The PHP8.3/GD and MariaDB10.11 families are available through Ubuntu24.04 packages. Verify Composer is2 and its runtime API satisfies composer.lock. Build assets with Node24.14/npm11.9 or a Vite7-supported build environment; production needs no Node daemon. Lock files are the install authority.

Record `nginx -v`, `php8.3 -v`, `php8.3 --ini`, `php8.3 -m`, `php-fpm8.3 -v`, `mariadb --version`, `composer --version`, `supervisord --version`, `docker version`, `docker compose version`, `restic version` and package versions via `dpkg-query`. Require `composer check-platform-reqs --no-dev` to pass on the actual release. Independently inspect FPM's enabled GD/other extensions (CLI success does not prove FPM success). CLI workers need pcntl/posix for timeout/signals; OPcache is recommended for FPM.

Use the existing FPM socket `/run/php/php8.3-fpm.sock` or revise both pool/Nginx consistently. Start with four ondemand children, idle timeout10s, max_requests500; measure real memory before increasing. The `deploy/php-production.ini.example` and `deploy/mariadb.cnf.example` are configuration drafts. MariaDB must bind127.0.0.1, use a localhost-only least-privilege application user and never grant public `%` access in production. Local WSL test grants are not a production recommendation.

## SSH, firewall and permissions

Create the dedicated deploy user/key through Hostinger's trusted access path; verify a second key-authenticated SSH session before disabling password/root login or enabling the firewall. No private key/password is stored in this repository. The sudo user and Docker access are privileged; do not grant Docker socket access to www-data.

Allow the confirmed SSH port (OpenSSH UFW profile assumes22),80 and443 only, then inspect `ufw status verbose` and `ss -lntp`. Do not expose3306/3000. Docker published-port rules require separate inspection; loopback binding in the compose is the WAHA boundary. Keep databases/provider reachable through localhost only. Install security updates in reviewed maintenance windows; confirm services survive an approved reboot.

Use immutable release directories under `/var/www/wholesale-order-system/releases/`, shared/.env and shared/storage, and current symlink. Source/vendor/assets remain owned by deploy and not writable by www-data. Storage and release-local bootstrap/cache are writable by www-data. Environment files mode0640 with controlled deploy/www-data group; backup credentials/root repository password mode0600 in a root-owned0700 directory. Preserve the initialized APP_KEY and securely back it up.

## Database and release

Before production data, run the [guarded real-engine harness](REL-W02-GATE-B2-DATABASE-VERIFICATION.md) in its exact dedicated verification database, with development dependencies available in an isolated validation checkout. Never point `phpunit.mysql.xml` at production or loosen DatabaseGuard. Once it passes on the VPS engine, provision a separate production database/user.

Build/install the selected release with committed locks:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer check-platform-reqs --no-dev
npm ci
npm run build
```

Run Composer as deploy, not root. Dev PHPUnit/Pint runs belong in the validation checkout/build pipeline before the no-dev install; production installation removes those tools. Link shared environment/storage before the reviewed migration. APP_ENV=production, APP_DEBUG=false, real HTTPS APP_URL, SESSION_SECURE_COOKIE=true, SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION=database, DB_QUEUE_RETRY_AFTER=90, WHATSAPP_DRIVER=waha, WHATSAPP_TIMEOUT=10, WHATSAPP_CONNECT_TIMEOUT=3, positive webhook tolerance300. Keep WHATSAPP_ENABLED=false and campaigns draft until [live WAHA checks](REL-W02-WAHA-PRODUCTION.md) pass.

Follow [the existing release runbook](REL-W02-PRODUCTION-RUNBOOK.md) for backup, reviewed migrate/limited seeding, config/route/view caches, symlink activation, queue:restart and schedule:interrupt. No migrate:fresh/demo seeding or APP_KEY regeneration on production. Supervisor starts one priority worker; cron runs schedule:run each minute for its sub-minute task. Validate config syntax and actual file/service ownership on-host before activation.

## Domain and HTTPS

The final domain is pending. `deploy/nginx.conf.example` and `deploy/nginx.tls.conf.example` contain only `__PRODUCTION_DOMAIN__`, not a live/temporary domain. Do not install a placeholder configuration or create a WAHA session pointing at a placeholder webhook. Once supplied, verify A/AAAA records resolve to the correct VPS; remove incorrect AAAA records rather than allowing intermittent failures. Provision a trusted certificate with the chosen ACME client, then validate `nginx -t`, document root current/public, HTTP→HTTPS, secure session cookies and renewal dry run. No public WAHA reverse proxy is needed.

## Encrypted backups and real restore drill

`deploy/backup.sh` is a prepared daily MariaDB/shared-storage backup using restic encrypted off-server HTTPS S3 storage. Install as `/usr/local/sbin/wholesale-backup` mode0700 and configure `deploy/backup.env.example` privately after repository provisioning. Root-only mysql.cnf supplies backup-account credentials without command-line passwords; root-only restic-password protects the repository key. Initialize the new repository once, never reset an existing backup repository. The example cron runs at02:15 server time; record timezone and rotate the log.

The script checks root ownership/mode0600, remote configuration, overlap lock, transactional dump with pipefail and gzip integrity, then remote backup and7daily/4weekly/3monthly retention. It deletes only its mktemp stage under the fixed backup root. Local syntax and source review have passed; no off-server upload, restic installation or restore has occurred. [Restic retention semantics](https://github.com/restic/restic/blob/master/doc/060_forget.rst).

Online backups do not freeze storage/DDL. Schedule reviewed migrations outside the dump window; for a release checkpoint, pause writes/worker and capture DB/storage together. Product objects on R2 require a separate object backup/versioning plan. WAHA session/media volumes require the separate stopped-service encrypted backup described in the WAHA guide; application backup does not include them.

Real drill after backup endpoint credentials exist:

1. Record the chosen remote snapshot ID, run repository integrity checking, restore that snapshot into an isolated directory/database using restore-only credentials. Sending remains disabled and no live WAHA session is attached.
2. Restore SQL into the isolated empty DB and storage into an isolated release. Preserve APP_KEY for encrypted data; use an isolated APP_URL. Verify table counts and FK integrity, order/item snapshots, Admin login, hidden-price/stock privacy and product media. Test actual restored R2 objects if active.
3. Record elapsed recovery time and loss window from backup cadence, and identify the exact release/DB/storage snapshot combination. A successful restic command alone is not the required application restore drill.

Code rollback: pause worker/cron/campaigns, validate schema and queued payload compatibility, then repoint current to the prior immutable release and restart FPM/worker/scheduler. Where incompatible, reviewed forward fix or restored DB/storage is required. Restoring an older queue can resurrect sent pending work; reconcile receipts before restarting. This rollback is prepared, not tested on the unavailable VPS.

## Capacity acceptance

Official [Hostinger specifications](https://www.hostinger.com/vps-hosting): KVM2=2vCPU/8GB/100GB NVMe, KVM4=4vCPU/16GB/200GB NVMe. KVM4 is owner-approved. These specifications are not a workload benchmark.

Runtime drafts use four FPM children, one database queue worker, MariaDB and WAHA NOWEB. Initial buffer pool1GB is an unmeasured budget. Do not hard-cap WAHA memory before measuring reconnect/media peaks; an OOM can create unknown sends. Reserve OS/filesystem/backup headroom.

After agreeing a representative load, observe30minutes plus reconnect/backup overlap with approved test recipients. Proposed gates: sustained CPU<70%, available RAM≥25%, no OOM or ongoing swap-in/out, disk<70% including volumes/backups, no persistent FPM listen queue/DB lock waits, core checkout p95≤1second and zero unexpected order failures. Measure order queue oldest age separately from intentional campaign pacing. These are proposed acceptance targets, not observed results or capacity guarantees.

Read-only probes: `free -m`, `vmstat 1 60`, `df -h`, `docker stats --no-stream`, process RSS, private FPM status, MariaDB counters and queue pending age/failed counts. Record versions, workload/concurrency, duration, CPU/RAM/swap/disk/FPM/DB/queue/WAHA values. Do not log personal data or environment secrets. KVM2 remains a fallback only after equivalent constrained-hardware tests demonstrate adequate headroom; KVM4 measurements alone do not prove KVM2 sufficiency.
