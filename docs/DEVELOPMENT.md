# Development and review

## Reproducible inputs

Install dependencies from their lockfiles with `composer install` and `npm ci`. Do not upgrade dependencies as a side effect of an unrelated change. The storefront asset build imports Filament theme CSS from Composer's `vendor/` directory, so PHP dependencies must be installed before `npm run build`.

Follow [the local installation](../README.md#local-installation) against an empty database dedicated to this checkout. No production configuration, customer export or provider session is needed for routine review.

## Commands and their scope

| Command | Scope |
| --- | --- |
| `php artisan serve` | Local Laravel HTTP server |
| `npm run dev` | Vite development assets |
| `npm run build` | Storefront and Filament production assets |
| `composer dev` | Concurrent HTTP server, default queue listener, local log tail and Vite |
| `composer test` | Clears configuration cache, then runs Laravel tests |
| `php artisan test --filter=OrderCreationTest` | Example targeted business regression |
| `php vendor/bin/pint --test` | PHP formatting check without rewriting source |
| `node --test tests/Frontend/useTranslations.test.mjs` | Focused translation replacement tests |
| `composer validate --strict --no-check-all` | Manifest/lock consistency; preserve the intentional exact Filament pin |
| `composer check-platform-reqs` | Installed dependency PHP/extension requirements |
| `git diff --check` | Changed-line whitespace validation |

`composer dev` listens on the default queue; it does not consume WhatsApp named queues. Use the priority worker in [the messaging guide](WHATSAPP.md#workers-and-scheduler) when deliberately testing that integration. `composer setup` also creates configuration, generates an application key and runs migrations, so use the explicit installation sequence when the database/environment already exists.

## Isolating default tests

[`phpunit.xml`](../phpunit.xml) selects Unit and Feature suites and configures SQLite `:memory:`, testing mode, array cache/session/mail, synchronous default queue and a disabled WhatsApp provider. It sets the test process memory limit to 512 MB because spreadsheet regressions can exceed the PHP CLI default of 128 MB. This does not change application or worker memory settings. Provider tests use fakes or mocked HTTP responses. Do not connect a real provider for them.

PHPUnit's default environment settings are not forced; process-level variables can override them. Run in an isolated development checkout with no production `.env` and no generated configuration cache. Set the environment explicitly if the shell inherited unrelated variables:

```powershell
$env:APP_ENV = 'testing'
$env:APP_CONFIG_CACHE = 'bootstrap/cache/testing-unavailable.php'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'array'
$env:WHATSAPP_ENABLED = 'false'
php artisan test
```

Use a dedicated terminal for these overrides. On macOS/Linux, set the corresponding environment values in a dedicated shell before invoking the test command. A missing GD/intl/SQLite extension is an environment failure; enable it rather than weakening relevant tests.

## MySQL-compatible verification

[`phpunit.mysql.xml`](../phpunit.mysql.xml) runs [`tests/Production/`](../tests/Production) separately from the fast suites. Despite its directory name, this is a **disposable verification database harness**, not a production test command.

[`DatabaseGuard`](../tests/Production/DatabaseGuard.php) checks the explicit opt-in, MySQL driver, configured database name and server-selected database name. The allowed name is `wholesale_rel_b1_verify_20260930`. Keep it intact: tests intentionally reset schema/data inside that exact database.

Provision that database on an isolated MariaDB 10.11 instance with a user scoped only to it. The suite explicitly expects MariaDB, strict SQL mode, InnoDB and `utf8mb4_unicode_ci`; it is not a generic compatibility test for every MySQL service. Supply its host/port/user/password privately. Ensure `DB_URL` is empty and do not reuse a live application's credentials. The PHPUnit configuration forces the database name, verification opt-in and disabled WhatsApp provider.

```bash
php vendor/bin/phpunit -c phpunit.mysql.xml
```

The harness covers real-engine constraints and subprocess concurrency around customer matching, checkout/order numbers, confirmation and delivery. Passing SQLite tests cannot substitute for this check when changing schema or concurrency behavior. A guard refusal means the environment does not match; do not bypass it.

## Development conventions

- Keep business mutations authoritative on the server and cover failure cases: stale variants, invalid quantities, duplicate input, forbidden transitions and privacy leaks.
- Extend the existing model/controller/support boundary before introducing generic repositories, services or another API layer.
- Storefront changes belong in Vue/Inertia; Admin changes belong in Filament.
- Preserve Arabic/English copy in `lang/`, test bidi-sensitive identifiers and review both locale layouts.
- Expose explicit public props instead of raw product/customer models. Retain hidden-price and stock-privacy regressions.
- Keep schema changes incremental. Explain backfill, compatibility and rollback constraints before applying them to populated databases.
- Review relationship loading for actual list access patterns. Measure queries/timing before claiming performance improvement.
- Imports need row-level errors and identifier preservation. Exports need leading-zero, zero-value and formula-safe checks.
- Keep credentials, dumps, logs, uploads, local screenshots and provider state outside Git.

## Extending the application

| Change | Start with | Relevant verification |
| --- | --- | --- |
| Catalog | [`CatalogController`](../app/Http/Controllers/CatalogController.php), [`CatalogPresenter`](../app/Support/CatalogPresenter.php), Vue catalog pages | Catalog, product details, price/stock privacy |
| Cart/quantities | [`Cart`](../app/Support/Cart.php), [`CartController`](../app/Http/Controllers/CartController.php), checkout validation | Cart, optional-color/size and order creation |
| Order workflow | [`Order`](../app/Models/Order.php), [`OrderItem`](../app/Models/OrderItem.php), order-management resources | Transitions, editing, delivery, snapshots and concurrency |
| Admin | [`app/Filament/`](../app/Filament) | Access, resource actions, dashboard and exports |
| Imports/exports | [`app/Support/Imports/`](../app/Support/Imports), [`app/Support/Exports/`](../app/Support/Exports) | Product/customer imports and actual XLSX assertions |
| Messaging | [`app/Support/WhatsApp/`](../app/Support/WhatsApp), gateway and job | Webhook, consent, dispatch races, templates and provider HTTP tests |

## Browser review journey

Use synthetic data and disabled external sending:

1. Create configured colors/sizes and a public-price product plus a request-price product.
2. Browse catalog filters and product pages in Arabic/English and a real mobile viewport. Inspect network/page props for hidden-price and reference-stock leakage.
3. Add quantities, edit cart and check validation when selections become unavailable or distributions are invalid.
4. Submit checkout with new and returning mobile numbers; check customer-code preservation. Refresh the success URL and confirm no extra order was created.
5. Review/edit the new order in Admin, confirm, then record partial and complete fulfillment.
6. Check outstanding production quantities after each delivery and compare a printed order with stored snapshots.
7. Download XLSX and inspect identifiers, zero values, item grain and delivery quantities.
8. Verify an anonymous browser cannot access Admin operations. Separate provider checks use isolated state and approved test recipients.

Automated tests and asset compilation support review; they do not establish browser accessibility, production capacity or backup recoverability. There is no checked-in CI workflow or browser automation suite in this snapshot.

## Repository access

Treat `main` as the integration source for reviewed changes. Work on a separate branch, keep diffs focused and record relevant checks. Branch protections are GitHub settings and are not asserted by this documentation.

Removing a private file changes the current snapshot only. Old commits, tags, other branches and existing clones can retain earlier content. Sharing requires considering history as well as the working tree; collaborator permissions are repository-wide.
