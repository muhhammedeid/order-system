# REL-W02 Gate B2 local database regression verification

Scope: rerun the existing production-engine regression suite after the Gate B2 WAHA hardening and mechanical formatting changes. No harness or application code was changed by this verification task. No live VPS, production database, or external WhatsApp provider was contacted.

## Environment and safety

- MariaDB: `10.11.14-MariaDB-0ubuntu0.24.04.1`, local Ubuntu 24.04 WSL service.
- PHP: `8.3.33`; PHPUnit: `11.5.56`, Windows runner.
- Temporary database: `wholesale_rel_b1_verify_20260930`, retained from Gate B1 and containing only regression fixtures.
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`.
- The unchanged `DatabaseGuard` requires the verification flag, the `mysql` connection, and the exact temporary database in both configuration and `SELECT DATABASE()`. Credentials remain in the existing private environment and are not printed or committed.
- The suite checks InnoDB and strict SQL mode. It rebuilds only this approved temporary schema, then isolates fixtures while preserving constraints. The actual provisioned VPS engine remains unverified.

The local WSL startup initially reported a systemd user-session warning and a missing MariaDB socket. A subsequent service-status check confirmed MariaDB active before the database metadata check and test run. This was a local startup issue, not a database regression.

## Command and result

```powershell
C:/php83/php.exe vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml --testdox --log-junit storage/app/rel-b2-mysql-results.xml
```

**PASS — 7 tests, 69 assertions, 0 failures, 0 errors, exit code 0.** Runtime: **03:59.386**, reported memory **62 MB**. All seven unchanged tests passed. The JUnit report independently confirms 7 tests, 69 assertions, 0 errors/failures (aggregate test time 239.370749 seconds). `git diff --check` passed. The JUnit report is an ignored local runtime artifact; Gate B1 historical evidence remains unchanged.

## Covered behavior

The seven unchanged tests verify fresh migrations; generated optional-dimension uniqueness; unsigned values, CHECK constraints and foreign keys; historical Arabic snapshot/delivery and duplicate-phone upgrade fixtures; rollback/reapply of the three B1 migrations; and separate-process customer matching, stale-delivery rejection, order-state locking, duplicate-order-number enforcement and complete checkout transactions.

General historical rollback remains potentially lossy for NULL optional dimensions, long snapshots, and removed delivery detail. See the preserved [Gate B1 verification](REL-W02-DATABASE-VERIFICATION.md) for those limitations and the historical migration repairs.

## Cleanup

After the successful run, the exact verification-owned `tail -f /dev/null` helper was confirmed as PID 256 and stopped with SIGTERM; its owning command session closed. The named temporary database is retained for repeatable checks and contains no production records. No deployment or live-server action is authorized or performed here.
