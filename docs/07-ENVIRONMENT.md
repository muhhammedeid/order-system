# Wholesale Order System — Development Environment

Recorded after P00-W01 closure. This is the official local development environment for this project.

## Database — WSL MariaDB 10.11 (official)

The project development database is **MariaDB 10.11.14 running inside WSL (Ubuntu-24.04)**, reachable from Windows at `127.0.0.1:3306`.

- Do **not** attempt to migrate back to XAMPP MySQL unless explicitly requested.
- XAMPP ships `mysql.exe` (MariaDB 10.4) but its MySQL server is not the server this project uses.
- Database: `wholesale_order_system` (`utf8mb4` / `utf8mb4_unicode_ci`).
- Access: dedicated application DB user only (`wholesale_user`). Credentials are kept in the local `.env` file only and must never be committed.
- WSL-side administrative commands run via `wsl -- sudo -n mysql -e "..."`.

## PHP — XAMPP 8.2.12

- PHP CLI used for all artisan/composer commands: `C:\xampp\php\php.exe`.
- Required/enabled extensions beyond defaults:
  - `intl` — required by Filament 4.13.2.
  - `gd` — required for product image uploads (needed from P01-W01 onward).
  - Both were enabled in `C:\xampp\php\php.ini` during P00-W01.
- Note: the PHPUnit test suite runs on in-memory SQLite (`pdo_sqlite` is present); application runtime is MySQL only.

## Node / Frontend

- Node 24.14, npm 11.9, Vite 7, Tailwind CSS 4 (`@tailwindcss/vite`).
- Production build: `npm run build`.

## Admin Access (local)

- Login: `http://127.0.0.1:8000/admin/login` (`php artisan serve`).
- Local admin account created via `php artisan make:filament-user`.
- Credentials are stored only locally; never commit them anywhere.

## Manual QA

- Final visual browser verification of `/admin/login` is performed manually by the project owner.
