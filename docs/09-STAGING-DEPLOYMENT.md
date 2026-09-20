# Staging Deployment (P00-W02)

Free-tier staging environment for the Wholesale Order System.

## Architecture

```
GitHub (branch: staging)
        │  auto-deploy on push
        ▼
Render Free Web Service (Docker, Frankfurt)
  └─ Apache 2.4 + PHP 8.2 (mod_php) on 0.0.0.0:10000
       ├─ Laravel 12 + Vue 3 / Inertia production build (public/build)
       ├─ Filament assets published during the image build
       └─ deploy/entrypoint.sh: migrate → storage:link → staging:admin → config:cache → apache
        │  HTTPS terminated by Render
        ▼
TiDB Cloud Starter (MySQL-compatible, TLS public endpoint, Frankfurt)
        │
Cloudflare R2 (S3-compatible product image storage)
```

No Redis, no queue worker, no cron, no separate frontend service.

## TiDB setup

1. Create a TiDB Cloud Starter instance in the region closest to the Render region
   (for Render `frankfurt`, prefer an EU/Frankfurt TiDB region).
2. Create a dedicated staging database (for example `wholesale_order_staging`).
3. Generate the instance password. Save it; it is shown only once.
4. Networking: allow the public endpoint (Render Free has no static outbound IPs,
   so the allow list must permit public connections; TLS plus credentials protect access).
5. TiDB requires TLS. TiDB Cloud certificates chain to Let's Encrypt (ISRG Root X1),
   which is present in the container's system CA bundle, so staging uses:
   - `MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`
   - `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=true`
6. The username includes the TiDB account prefix, for example `<prefix>.root`.

Known TiDB differences confirmed during the smoke test are recorded in the package handoff.
The database does not enforce `CHECK` constraints unless `tidb_enable_check_constraint`
is enabled; application-level validation still applies.

## Cloudflare R2 setup

1. Create an R2 bucket (for example `mai-shoes-staging`).
2. Enable public access for the bucket and note its public URL
   (an `r2.dev` URL or a custom domain).
3. Create an R2 API token with object read/write access and note:
   - access key id, secret access key
   - the S3 endpoint, for example `https://<account-id>.r2.cloudflarestorage.com`
4. Staging sets `PRODUCT_IMAGES_DISK=r2`; local development keeps `public`.
5. Only product image uploads and product image URL generation use R2.

## Render setup

1. Render Dashboard → **New → Blueprint** → select this repository and branch
   `staging` (or create a Docker web service manually and mirror `render.yaml`).
2. `render.yaml` defines: `plan: free`, `region: frankfurt`, `healthCheckPath: /up`,
   `autoDeployTrigger: commit`, and the environment variables below.
3. Variables marked **secret** below are not stored in the repository; Render prompts
   for them on creation and stores them encrypted.
4. If the deployed URL differs from `https://wholesale-orders-staging.onrender.com`,
   update `APP_URL` to the real HTTPS URL and redeploy.

## Environment variables

Non-secret (in `render.yaml`):

`APP_NAME`, `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL`, `APP_LOCALE`,
`APP_FALLBACK_LOCALE`, `APP_MAINTENANCE_DRIVER`, `BCRYPT_ROUNDS`, `LOG_CHANNEL`,
`LOG_LEVEL`, `DB_CONNECTION`, `DB_PORT`, `MYSQL_ATTR_SSL_CA`,
`MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`, `SESSION_DRIVER`, `SESSION_LIFETIME`,
`SESSION_SECURE_COOKIE`, `CACHE_STORE`, `QUEUE_CONNECTION=sync`,
`FILESYSTEM_DISK`, `PRODUCT_IMAGES_DISK=r2`, `MAIL_MAILER`, `BROADCAST_CONNECTION`, `PORT`.

Secret (Enter in the Render Dashboard; never commit):

`APP_KEY`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_URL`,
`STAGING_ADMIN_NAME`, `STAGING_ADMIN_EMAIL`, `STAGING_ADMIN_PASSWORD`.

`APP_KEY` must be a dedicated staging key generated with
`php artisan key:generate --show` (includes the `base64:` prefix).

## Admin bootstrap

`php artisan staging:admin` runs automatically in `deploy/entrypoint.sh`:

- Creates the admin from `STAGING_ADMIN_*` when the account does not exist.
- Leaves an existing account and its password unchanged (cold starts never reset a changed password).
- No-op when the variables are absent.
- Refuses to run in production; never prints the password.

## Deploy / redeploy procedure

1. Push to the `staging` branch (or use **Manual Deploy** in the Render Dashboard).
2. Render builds the Docker image, then runs the start command. The entrypoint applies
   pending migrations (`php artisan migrate --force`), links storage, bootstraps the admin,
   caches configuration, and starts Apache.
3. Verify `/up` returns 200, then check the storefront and `/admin/login`.

## Rollback procedure

- Render Dashboard → service → **Deploys** → roll back to a previous deploy
  (the two most recent deploys are available on the Free plan). This rolls back code only.
- Database migrations are forward-only and idempotent; there is no automatic schema rollback.
  For a bad schema change, ship a forward fix.
- Rollback of the branch itself: revert the offending commit on `staging` and push.

## Free-tier limitations

- The service spins down after 15 minutes without traffic; the next request takes about a minute.
- The container filesystem is ephemeral and there is no persistent disk.
  Product images survive because they are stored in R2; database data survives in TiDB.
- No shell access and no one-off jobs on Free; all setup runs in the entrypoint.
- No pre-deploy command on Free; migrations run in the container start command.
- 750 instance hours per workspace per month; the service is suspended if exhausted.
- Free services cannot listen on ports 18012, 18013, 19099; staging uses 10000.

## Local development behavior

- Local development is unchanged: product images keep using the `public` disk
  (`PRODUCT_IMAGES_DISK=public`) and `/storage/...` URLs.
- `bootstrap/app.php` trusts forwarded proxy headers, which is inert without a proxy.
- The staging admin command no-ops without `STAGING_ADMIN_*` variables.
- `composer install`, `npm run build`, `php artisan serve`, and the PHPUnit suite
  are unaffected.
