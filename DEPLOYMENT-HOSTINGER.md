# HSMS — Hostinger Deployment Guide

Target: **`hsms.satvscript.com`** served from **`public_html/hsms`** on Hostinger Premium.

Laravel keeps its app code outside the web root and exposes only the `public/` folder.
On shared hosting we achieve that by pointing the subdomain's document root at
`public_html/hsms/public`.

---

## 1. Build assets locally first
Hostinger shared plans usually lack Node. Build on your machine and upload the result:
```powershell
npm install
npm run build          # generates public/build/
```

## 2. Upload the project
Upload the entire project to `public_html/hsms` (via Git, SSH, or File Manager / FTP),
**including** `public/build/`. Do **not** upload `.env`, `/vendor`, or `/node_modules`.

```
public_html/hsms/
├── app/  bootstrap/  config/  database/  routes/  resources/  storage/
├── public/            <-- web root for the subdomain
└── composer.json ...
```

## 3. Point the subdomain at /public
In hPanel → **Domains → Subdomains**, create `hsms` and set its **document root** to:
```
public_html/hsms/public
```
> If hPanel won't let you change the document root, instead create the subdomain pointing
> at `public_html/hsms` and add a `.htaccess` in `public_html/hsms` that rewrites into
> `public/` (sample below).

## 4. Install PHP dependencies (SSH)
Enable SSH in hPanel, then:
```bash
cd ~/public_html/hsms
composer install --no-dev --optimize-autoloader
```
> No SSH? Run `composer install` locally and upload the `vendor/` folder too.

## 5. Environment
```bash
cp .env.example .env
nano .env
```
Set production values:
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hsms.satvscript.com

DB_DATABASE=uXXXX_hsms
DB_USERNAME=uXXXX_hsms
DB_PASSWORD=********

MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=no-reply@hsms.satvscript.com
MAIL_PASSWORD=********
```
Create the MySQL DB + user in hPanel → **Databases**, then:
```bash
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 6. SSL
hPanel → **SSL** → install the free Let's Encrypt certificate for the subdomain, then
force HTTPS (`APP_URL` uses `https://`).

## 7. Scheduler & Queue (reminders, backups)
hPanel → **Advanced → Cron Jobs**, add one cron running every minute:
```bash
/usr/bin/php /home/uXXXX/public_html/hsms/artisan schedule:run >> /dev/null 2>&1
```
For queued mail/WhatsApp, add a periodic (or persistent) worker:
```bash
/usr/bin/php /home/uXXXX/public_html/hsms/artisan queue:work --stop-when-empty
```

## 8. Permissions
```bash
chmod -R 775 storage bootstrap/cache
```

---

### Sample fallback `.htaccess` (only if doc-root can't be changed)
Place in `public_html/hsms/.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

### Post-deploy checklist
- [ ] `https://hsms.satvscript.com` loads the login page
- [ ] Super Admin + demo hostel logins work
- [ ] `php artisan migrate:status` all run
- [ ] Cron for `schedule:run` active
- [ ] `APP_DEBUG=false`, SSL forced
- [ ] Change the seeded Super Admin password immediately
