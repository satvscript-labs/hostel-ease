# HSMS — Deploy to Hostinger (File Manager, no SSH)

Everything is pre-built. Upload one zip, import one SQL file, edit 4 DB lines, visit one URL.

**Files to upload (in `D:\xampp\htdocs\`):**
- `hsms-deploy.zip` (14 MB) — the app, incl. `vendor/` + built assets (no Composer/Node needed on server).
- `hsms-database.sql` (94 KB) — your live database (Sunrise + Shraddha hostels, all students, logins).

**Domain:** `hsms.satvscript.com` (subdomain + SSL already created ✅).

> **Database — two choices:**
> **(A) Import my SQL dump (recommended — keeps your reviewed data):** in Step 2 import
>   `hsms-database.sql`. The installer in Step 6 detects existing data and will NOT overwrite it.
> **(B) Fresh seed:** skip importing; the Step 6 installer creates the schema + demo data itself.
> Either way you still run Step 6 (it also links storage + caches).

---

## Two bundles available
- **`hsms-deploy.zip`** (14 MB) — full bundle **with `vendor/`**. Use for **File Manager / no SSH**
  (the steps below). Nothing to install on the server.
- **`hsms-deploy-no-vendor.zip`** (0.5 MB) — everything **except `vendor/`**. Use only if you have
  **SSH**; after extracting, run:
  ```bash
  cd ~/public_html/hsms
  composer install --no-dev --optimize-autoloader
  php artisan migrate --force --seed
  php artisan storage:link
  php artisan config:cache && php artisan view:cache
  ```
  Then skip Step 6 (web installer) — you've already migrated. Still do Steps 1–5, 7–9.

---

## Step 1 — Set PHP version (hPanel)
hPanel → **Advanced → PHP Configuration** → set PHP to **8.2 or 8.3**.
Under **PHP extensions**, make sure these are ticked: `pdo_mysql`, `mbstring`, `openssl`,
`fileinfo`, `gd`, `zip`, `dom`, `curl`, `xml`. (All standard on Hostinger.)

## Step 2 — Create the database + import (hPanel → Databases → MySQL)
1. Create a database and a user, give the user **all privileges**. Note the three values
   (Hostinger prefixes them, e.g. `u123456789_hsms`):
   - Database name · Database username · Database password
2. **(Recommended)** Import your data: click **phpMyAdmin** next to the new database →
   **Import** tab → choose **`hsms-database.sql`** → **Go**. You should see the tables fill
   with both hostels, students, payments, etc.
   *(Skip this import if you'd rather start with fresh demo data — Step 6 will create it.)*

## Step 3 — Upload & extract (hPanel → File Manager)
1. Go into **`public_html/hsms`** (create the folder if it isn't there).
2. **Upload** `hsms-deploy.zip` into it.
3. **Extract** it here, then delete the zip. You should now see `app/`, `public/`, `vendor/`,
   `.env.hostinger`, etc. directly inside `public_html/hsms`.

## Step 4 — Create the .env
In File Manager, inside `public_html/hsms`:
1. **Rename** `.env.hostinger` → `.env`  (enable "show hidden files" if you don't see it).
2. **Edit** `.env` and set the 4 database lines from Step 2:
   ```
   DB_DATABASE=u123456789_hsms
   DB_USERNAME=u123456789_hsms
   DB_PASSWORD=your-db-password
   ```
   (Optional: set the `MAIL_PASSWORD` if you created the `no-reply@…` mailbox, and change
   `SUPERADMIN_PASSWORD` to your own.)
   `APP_KEY` is already filled in — leave it.

## Step 5 — Point the subdomain at /public
hPanel → **Domains → Subdomains** (or **Domains** → manage `hsms.satvscript.com`) → set the
**Document Root** to:
```
public_html/hsms/public
```
> Can't change it? Skip this — the bundle includes a fallback `.htaccess` in
> `public_html/hsms` that forwards requests into `public/` automatically.

## Step 6 — Run the one-time installer
Open this URL **once** in your browser (token is already in your `.env`):
```
https://hsms.satvscript.com/__install/e0a10b35816ffaac1781cc2bcbd38dac
```
You'll see a log: migrations run, database seeded, storage linked, caches built, and a line like
`DB OK — hostels: 1, users: 2`. If you see that, you're live.

## Step 7 — Secure it (important)
Edit `.env` again and **blank the token**:
```
SETUP_TOKEN=
```
Then visit `https://hsms.satvscript.com/__install/anything` once — it should now show **404**
(installer disabled). Also delete `DEPLOY-NOW.md` and `.env.hostinger` if it remains.

## Step 8 — Log in
`https://hsms.satvscript.com`
- **Super Admin** → mobile `9999999999`, password `ChangeMe@123` (change it from Admins page)
- **Demo Hostel Admin** → `9876543210` / `Password@123` (delete the demo hostel later)

---

## Step 9 — Scheduler (for rent generation, alerts, nightly backup)
hPanel → **Advanced → Cron Jobs** → add a job running **every minute**:
```
/usr/bin/php /home/uXXXXXXXX/public_html/hsms/artisan schedule:run >> /dev/null 2>&1
```
(Find your exact PHP path + home path in hPanel; some accounts use `/opt/alt/php82/usr/bin/php`.)
This drives: monthly rent rows (1st), daily notifications (08:00), nightly DB backup (02:00).

## Notes
- **Backups module**: if "Create Backup Now" errors about mysqldump, set in `.env`:
  `DB_DUMP_BINARY=/usr/bin/mysqldump` (ask Hostinger support for the exact path) then retry.
- **After any .env change** the app picks it up automatically here because we don't keep a stale
  config cache; if something looks off, re-run the installer URL (re-enable token briefly) which
  rebuilds caches.
- **File permissions**: `storage/` and `bootstrap/cache/` must be writable (755/775). File
  Manager → select folder → Permissions if needed.

## Troubleshooting
| Symptom | Fix |
|--------|-----|
| 500 error, blank page | Set `APP_DEBUG=true` in `.env`, reload to see the error; revert after. Check `storage/logs/laravel.log`. |
| "No application encryption key" | `APP_KEY` line got cleared — restore it from `.env.hostinger`. |
| CSS/JS missing (unstyled) | Confirm `public/build/` uploaded and doc root points to `/public` (or fallback `.htaccess` present). |
| 419 Page Expired on login | Clear browser cookies for the domain; ensure `SESSION_SECURE_COOKIE=true` and HTTPS. |
| DB connection refused | Re-check the 4 DB lines; on Hostinger `DB_HOST=127.0.0.1` (or `localhost`) is correct. |
