# HostelEase

A multi-tenant **hostel / PG management platform** built on **Laravel 12**, MySQL 8 and a
Bootstrap 5 front end. One codebase runs many hostels; each hostel can run many **branches**,
and every branch is billed on its own subscription.

---

## Who uses it

| Role | Scope |
|------|-------|
| **Super Admin** | The platform operator. Manages customer accounts, hostels, branches, subscriptions, discounts, activity logs and database backups. Not tenant-scoped — sees everything. |
| **Hostel Admin (owner)** | Owns an account. Runs their branches, creates sub-user logins, manages their own subscription and settings. Full access inside their tenant. |
| **Sub-users** | Logins the owner creates: **Manager**, **Accountant**, **Warden**, **Viewer (read-only)**. Each is limited to a set of feature areas by the access matrix in [config/hostelease.php](config/hostelease.php). |

Login username is the **mobile number** (10 digits, normalised to `+91…`). Session auth for the
web app; Sanctum tokens are reserved for a future mobile client.

---

## What's in the app

### Property

Floor / room / bed layout builder with auto bed generation, a colour-coded **Property Board**
(empty / occupied / reserved / maintenance), per-bed occupancy history, and assign / release /
transfer handled transactionally by [`BedAssignmentService`](app/Services/BedAssignmentService.php).

### People

- **Students** — full profile (contacts, guardian, college fields, Aadhaar, documents, photo),
  QR profile link, fee settings per student, and a bed-assignment history.
- **Registrations** — a public per-hostel token/QR link students fill in themselves; the admin
  approves or rejects, and approval creates the student record.
- **Front Desk** — visitor check-in / check-out register and a complaints/tickets tracker.

### Finance

One invoice model behind everything owed, one payment engine behind everything received.

- **Invoices & Dues** — rent, semester/yearly fees, AC bills and ad-hoc charges, generated on a
  schedule ([`hostel:generate-invoices`](app/Console/Commands/HostelEaseGenerateInvoices.php)) with
  proration handled by [`ProrationService`](app/Services/ProrationService.php).
- **Transactions** — [`PaymentService`](app/Services/PaymentService.php) issues the receipt number,
  settles the invoice, writes the audit entry. Receipts print, export to PDF, and go out over
  WhatsApp or email.
- **AC Bills** — meter-reading based, split equally or among selected occupants, remainder pushed
  onto the last share so a split always reconciles.
- **Expenses**, **Security Deposits**, **Pocket Money**, and configurable **Payment Modes**.

### Staff & Ops

Staff directory with salary payments (mirrored into expenses) and day-wise attendance. Removing a
staff member keeps their salary history intact.

### Presence — In/Out register

Integration with a **TrueFace1000EW** face/RFID gate device via the vendor's **iDMS** middleware.
[`PresenceService`](app/Services/Presence/PresenceService.php) polls for punches every minute,
debounces double-scans, and drives live **Students** and **Staff** boards showing who is in, who is
out and for how long. Includes a gate log with export, per-person history with manual corrections,
enrollment / re-push / revoke against the device, a quarantine queue for unmatched device IDs,
per-branch **curfew** alerts, and an on-leave marker. A `fake` adapter backs local dev and tests.

### Reports

Collection, income by mode, income vs expenses, pending fees with ageing buckets, occupancy, AC
bills, and presence in/out — all built by [`ReportService`](app/Services/ReportService.php) into one
normalised shape (headings / rows / summary tiles / chart), rendered through a single results view
with date-range filters and Print / PDF / Excel export.

### Platform (Super Admin)

Customer accounts with a billing "Account 360" terminal (renew, add branch, align dates, comp,
override, suspend), volume + manual discounts, Razorpay-backed subscription orders and invoices,
login/activity audit feed, and scheduled database backups.

### Everywhere

Global instant search, a notification bell fed by a daily alert generator, multi-language
(EN / HI / GU), branch switcher, activity logging on mutating requests, and a PWA shell.

---

## Tech stack

- **Backend:** Laravel 12, PHP 8.2+, MySQL 8, Sanctum, Queue & Scheduler
- **Frontend:** Bootstrap 5 + SCSS, Alpine-style inline JS, Chart.js, Select2, SweetAlert2, DataTables (bundled via Vite)
- **PDF / Excel:** barryvdh/laravel-dompdf, maatwebsite/excel
- **Images / QR:** intervention/image, simplesoftwareio/simple-qrcode
- **Payments:** Razorpay (platform subscriptions only — no gateway for hostel fee collection)

---

## Running it locally

```bash
composer install
npm install && npm run build
cp .env.example .env          # set DB credentials
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Full local setup notes (XAMPP paths, mysqldump binary, queue/scheduler): [_artifact/Local_Development.md](_artifact/Local_Development.md).

### Seeded logins

| Role | Mobile | Password |
|------|--------|----------|
| Super Admin | `8140740705` | `ChangeMe@123` *(override via `SUPERADMIN_*` env)* |
| Hostel owner / demo staff | `9876543210` | `password` |

### Tests

```bash
php artisan test
```

---

## Architecture notes

- **Tenancy.** Every hostel-owned model uses `BelongsToHostel`, which applies a global `TenantScope`
  and auto-fills `hostel_id`. The active branch is bound per request by `SetTenant` from the logged-in
  user; Super Admin stays unbound. `Hostel` itself can't be scoped — its boundary is the explicit
  `canAccessHostel()` check.
- **Authorization.** Three layers: `role:` (super_admin / hostel_admin / staff), `access:<area>`
  (the sub-user matrix), and `subscription.active` (the paywall). Presence uses its own explicit
  allow-list, `presence.access`, because read-only Viewers must be excluded from it.
- **Opaque URLs.** Models that appear in a route use `HasPublicId` — a 26-char ULID route key, so
  records can't be enumerated. The integer `id` remains the primary key and stays in posted form
  fields. `PublicIdHardeningTest` fails if a model silently loses the trait.
- **Private uploads.** Nothing user-uploaded is served from `public/`. Every file goes through
  `SecureFileController`, which re-checks the caller's access area. Aadhaar numbers are encrypted at
  rest and each reveal writes an audit entry.
- **Config over magic strings.** Domain enums, the access matrix, pricing, and trial/grace windows
  live in [config/hostelease.php](config/hostelease.php); gate-device settings in
  [config/presence.php](config/presence.php).
- **Helpers/prefixes.** Helper functions `hostelease_*`, CSS tokens `--he-*`, Blade components
  `<x-he-*>`.

Conventions are written down in [_artifact/development_standards.md](_artifact/development_standards.md)
and [_artifact/ui_conventions.md](_artifact/ui_conventions.md); per-module design notes live under
[_artifact/](_artifact/).

---

## Scheduled work

| Command | When |
|---------|------|
| `hostelease:backup --prune=30` | 02:00 daily |
| `hostel:generate-invoices` | 01:00 daily |
| `hostelease:process-subscription-lifecycle` | 07:30 daily |
| `hostelease:generate-notifications` | 08:00 daily |
| `hostelease:presence-sync` | every minute |
| `hostelease:presence-curfew-check` | every 15 minutes |
