# HSMS — Installation Guide (Local / XAMPP)

## 1. Prerequisites
Your environment currently has **PHP 8.2.12 (XAMPP)** and **MySQL** — both fine for
Laravel 12. You still need two tools that are **not yet installed**:

| Tool | Why | Get it |
|------|-----|--------|
| **Composer** | Install PHP dependencies (Laravel framework, packages) | https://getcomposer.org/Composer-Setup.exe |
| **Node.js LTS** | Build front-end assets (Vite, Bootstrap, Chart.js…) | https://nodejs.org |

> During Composer setup, point it at `D:\xampp\php\php.exe`.

Verify after install (in a **new** terminal):
```powershell
php -v
composer --version
node -v
```

## 2. Install dependencies
From `D:\xampp\htdocs\hsms`:
```powershell
composer install
npm install
```

## 3. Environment file
```powershell
copy .env.example .env
php artisan key:generate
```
Edit `.env` and set your DB + super-admin seed credentials:
```dotenv
DB_DATABASE=hsms
DB_USERNAME=root
DB_PASSWORD=

SUPERADMIN_MOBILE=9999999999
SUPERADMIN_PASSWORD=ChangeMe@123
```

## 4. Create the database
Open phpMyAdmin (http://localhost/phpmyadmin) → create an empty DB named **`hsms`**
(utf8mb4 / utf8mb4_unicode_ci). Or via CLI:
```powershell
& "D:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE hsms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 5. Migrate + seed + assets
```powershell
php artisan migrate --seed
php artisan storage:link
npm run build
```

## 6. Run
```powershell
php artisan serve
```
Visit **http://localhost:8000** and log in with the demo credentials (see README).

### Dev mode (hot reload)
```powershell
npm run dev        # in one terminal
php artisan serve  # in another
```

## 7. Queue & Scheduler (for reminders/backups, when those modules land)
```powershell
php artisan queue:work
# Scheduler (cron-equivalent) — run every minute:
php artisan schedule:work
```

## Troubleshooting
- **`vendor/autoload.php` not found** → run `composer install`.
- **`@vite` manifest error** → run `npm run build` (or `npm run dev`).
- **Index/key length errors on migrate** → already mitigated via
  `Schema::defaultStringLength(191)` in `AppServiceProvider`.
- **419 Page Expired** → `php artisan key:generate`, clear browser cookies.
- **Permission/cache issues** → `php artisan optimize:clear`.
