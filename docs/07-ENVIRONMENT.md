# Wholesale Order System — Development Environment

Recorded after P00-W01 closure. This is the official local development environment for this project.

## Database — WSL MariaDB 10.11 (official)

The project development database is **MariaDB 10.11.14 running inside WSL (Ubuntu-24.04)**, reachable from Windows at `127.0.0.1:3306`.

- Do **not** attempt to migrate back to XAMPP MySQL unless explicitly requested.
- XAMPP ships `mysql.exe` (MariaDB 10.4) but its MySQL server is not the server this project uses.
- Database: `wholesale_order_system` (`utf8mb4` / `utf8mb4_unicode_ci`).
- Access: dedicated application DB user only (`wholesale_user`). Credentials are kept in the local `.env` file only and must never be committed.
- WSL-side administrative commands run via `wsl -- sudo -n mysql -e "..."`.

## PHP — PHP 8.3.33 (C:\php83)

- PHP CLI used for all artisan/composer commands: `C:\php83\php.exe` (PHP 8.3.33 ZTS).
- Active configuration file: `C:\php83\php.ini` (`extension_dir = ext` → `C:\php83\ext`).
- Enabled extensions required beyond defaults:
  - `intl` — required by Filament 4.13.2 (pagination/number formatting). Without it
    Filament pages fail with `RuntimeException: The "intl" PHP extension is required to
    use the [format] method`.
  - `pdo_sqlite` and `sqlite3` — required by the PHPUnit suite (in-memory SQLite). With
    both enabled, `php artisan test` / `vendor/bin/phpunit` run without `-d` flags.
  - `pdo_mysql` — application runtime database driver.
- `gd` is currently **not enabled** in `C:\php83\php.ini`. It was part of the original
  environment contract for product image uploads; re-enable it
  (`extension=gd`) if an image-processing path actually fails during QA.
- Note: application runtime is MySQL only; SQLite is used exclusively by the test suite.
- Historical note: earlier revisions of this document referenced `C:\xampp\php\php.exe`
  (XAMPP 8.2.12). That installation no longer exists on this machine.

## Node / Frontend

- Node 24.14, npm 11.9, Vite 7, Tailwind CSS 4 (`@tailwindcss/vite`).
- Production build: `npm run build`.

## Admin Access (local)

- Login: `http://127.0.0.1:8000/admin/login` (`php artisan serve`).
- Local admin account created via `php artisan make:filament-user`.
- Credentials are stored only locally; never commit them anywhere.

## Technical Decisions (MVP)

Schema/technical decisions that refine the approved schema now live in `08-SCHEMA-DECISIONS.md`.
- Development machine note: the WSL VM is stopped/started by Windows quickly; MariaDB inside WSL takes ~30-45 s to become ready after the VM boots. If the database refuses connections after idle, run `wsl -- sudo -n service mariadb start` (or `wsl -- sudo -n systemctl start mariadb`) and wait for readiness before running artisan commands.

## Manual QA

- Final visual browser verification of `/admin/login` is performed manually by the project owner.
