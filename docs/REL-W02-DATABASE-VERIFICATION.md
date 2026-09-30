# REL-W02 Gate B1 production-engine verification

## Safety boundary

This harness is separate from the default SQLite PHPUnit suites. It refuses to run unless `REL_B1_DATABASE_VERIFICATION=1`, the configured connection uses the `mysql` driver, and both Laravel's configured database and `SELECT DATABASE()` equal **`wholesale_rel_b1_verify_20260930`**. It recreates only that explicitly approved temporary schema. Do not repoint it at development, staging, or production. Credentials come from the local environment; none are committed or printed.

Before the first run, a read-only schema inventory confirmed that this database did not exist. It was then created as an empty UTF-8 temporary database and granted to the existing local application user without changing its password. No other schema was modified.

```powershell
C:/php83/php.exe vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml --no-progress
```

Keep the local WSL MariaDB service running throughout the command. The command needs a working `pdo_mysql` connection using the existing environment credentials. Never run it concurrently with another invocation of this harness.

## Environment and coverage

Local verification engine: MariaDB **10.11.14** on Ubuntu 24.04 (WSL), PHP **8.3.33** on Windows. Database charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`; Laravel enables strict SQL mode. The test asserts strict mode and InnoDB tables. This approximates the intended Ubuntu 24.04 MariaDB 10.11 deployment; the actual Hostinger production engine/version has not yet been supplied or inspected. It is not proof for every MySQL version, production collation, or production workload.

The dedicated suite checks:

- Fresh migrations, generated `color_key`/`size_key`, duplicate NULL and empty optional dimensions, unsigned quantities, both delivered-quantity CHECK constraints, restricted customer deletion, invalid foreign keys, and product/variant deletion preserving item snapshots.
- Upgrade from the schema immediately before per-color delivery tracking. Representative Arabic historical snapshots, multi-color partially delivered items, local/international duplicate phones and absent WhatsApp values are preserved. The color backfill keeps ambiguous historical delivery unallocated rather than guessing. Phone normalization keeps duplicate customers and matches the oldest deterministically; no risky unique-phone constraint is added.
- Rollback and reapply of the three new Gate B1 migrations, with historical records still present.
- Two independent PHP processes/connections competing against a parent-held InnoDB lock: equivalent-phone customer creation produces one identity; stale duplicate delivery increments only once; repeated order confirmation has only one winner.
- Two independent processes inserting the same order number: one succeeds and the unique constraint rejects the other with MariaDB error 1062. This isolates database collision enforcement; controller collision retry is covered by the ordinary order-creation regressions, not this insert test.
- Two independent processes invoke the real checkout transaction/retry boundary for equivalent customer phone formats, creating two distinct complete orders with one customer identity and preserved item snapshots. Reflection calls the existing private method directly; no database or application behavior is mocked.

Workers announce readiness before their database operation using randomly named temporary marker files, which the parent removes. The parent holds the relevant customer/order lock until both workers are ready, then releases it; this verifies competing connection behavior rather than invoking two model operations sequentially. Worker output contains only result codes and identifiers, not SQL, credentials, or personal data. File readiness avoids blocking Windows subprocess pipes while the parent owns a row lock.

## Rollback limitations

The historical optional-size/color migrations normalize NULL to empty strings during rollback; they cannot recover original NULL values. They also alter columns while generated indexes exist: the upgrade suite exercises their historical reset on representative current fixtures, but production data/engine still requires a reviewed rollback decision. Snapshot-shrinking rollback can fail with values longer than the old 255-character width. Historical color-delivery rollback removes detail rows. Therefore use a release-compatible forward fix or tested backup restoration when schema/data is incompatible with an old release; do not promise general lossless `migrate:rollback`.

The formerly empty variant-link `down()` now drops the constrained `product_variant_id` column; the full `migrate:reset` path in this suite checks that the column is removed before replay. Verification also found a real MariaDB rollback defect: dropping the `whatsapp_messages_order_id_occurred_at_index` before its foreign key fails with error 1553 because MariaDB uses that index to support the foreign key. Its `down()` now explicitly drops the foreign key, then the composite index, then the column. Both historical migration repairs are owned by the primary executor; the full suite rerun verifies them.

## Results

**Final result: PASS — 7 tests, 69 assertions, 0 failures/errors, exit code 0.** Runtime: **2 minutes 48.426 seconds**, peak reported memory **62 MB**. All seven cases listed above passed against the real MariaDB engine. The debug run also passed all seven cases after the migration repairs; the final normal run was repeated to obtain exact assertion counts and a machine-readable report.

Final command:

```powershell
C:/php83/php.exe vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml --testdox --log-junit storage/app/rel-b1-mysql-results.xml
```

The JUnit report is a local ignored runtime artifact. The fixed-name temporary database remains available for reruns; it contains only verification fixtures. `C:/php83/php.exe vendor/bin/pint tests/Production --test` and `git diff --check` passed after the harness corrections. PHP syntax checks passed for all three new PHP files. The earlier isolated equivalent-phone two-process customer test passed: **1 test, 8 assertions**, 56.632 seconds including fresh migrations.

Earlier checks were not all successful: a Windows process-pipe readiness stall required terminating only the harness PHP processes, releasing temporary database locks; file-based readiness then passed the isolated test. The generated-NULL fixture initially failed the current model's required-color validation, so it was corrected to a direct SQL fixture for historical schema constraint testing. A subsequent run passed the schema test but failed the historical reset with MariaDB error 1553 described above; the migration was repaired before rerunning. These were explicit failures and interruptions, not evidence of a completed production-engine suite.
