# HSMS — Hostel Management System

A multi-tenant **Hostel / PG Management SaaS** built on **Laravel 12**, MySQL 8 and a
Bootstrap 5 (blue theme) front end. Two roles: **Super Admin** (manages many hostels &
subscriptions) and **Hostel Admin** (runs a single hostel).

> Domain: `hsms.satvscript.com` · Deploy path: `public_html/hsms`

> **Revision note (2026-06-04):** Fees are no longer set on the Room. Each student's
> **fee amount + frequency (Monthly / Semester / Yearly, default Semester)** is chosen when
> assigning them to a bed (`bed_assignments.fee_amount` / `fee_frequency`). Monthly-frequency
> assignments drive the Monthly Rent auto-generator; semester/yearly are recorded in the
> Semester Fees module. The UI is mobile-responsive with an off-canvas sidebar drawer.
> (Some older "Module N details" below still mention room rent — superseded by this note.)

---

## ✅ What's in this foundation build

This is the **runnable foundation** the rest of the modules layer onto.

| Area | Status |
|------|--------|
| Project scaffold (Laravel 12, composer, vite, PWA) | ✅ Done |
| Full DB schema — 17 domain tables + framework + Sanctum | ✅ Done (migrations) |
| Eloquent models + relationships + soft deletes | ✅ Done |
| Multi-tenancy (global `TenantScope`, auto hostel scoping) | ✅ Done |
| Auth (mobile-number login, roles, Sanctum, session timeout) | ✅ Done |
| Role + subscription middleware, activity logging | ✅ Done |
| Bootstrap 5 blue theme, app shell, sidebar/topbar | ✅ Done |
| Super Admin dashboard (cards + Chart.js) | ✅ Done |
| Hostel Admin dashboard (cards, occupancy, alerts) | ✅ Done |
| Seeders (super admin + demo hostel with beds/students/payments) | ✅ Done |
| PWA (manifest + service worker + offline page) | ✅ Done |
| **Module 1** — Floors + Rooms (auto bed generation) + visual Bed Layout | ✅ Done |

#### Module 1 details
- **Floors:** CRUD with inline modal, room-count guard on delete.
- **Rooms:** full CRUD; [`BedGenerator`](app/Services/BedGenerator.php) auto-creates beds
  `B1..Bn` from the sharing count, and on edit grows/shrinks beds — **only empty beds are
  removed**, occupied/reserved beds are preserved so history is never lost.
- **Bed Layout:** floor-wise → room-wise color-coded bed grid (green/red/yellow/grey),
  occupancy %, floor filter, click-a-bed modal with tap-to-call / WhatsApp and
  empty/reserved/maintenance status changes (occupied is owned by Bed Assignment).
- Tests: [`RoomBedTest`](tests/Feature/RoomBedTest.php).

#### Module 2 details — Students
- Full CRUD ([`StudentController`](app/Http/Controllers/Admin/StudentController.php)) with photo
  upload, all contact fields (student/father/mother/guardian), Aadhaar, address, occupation,
  join/leave dates, status.
- Mobiles auto-normalised to 10 digits on input and rendered as clickable **+91 tap-to-call /
  WhatsApp** links via the [`<x-mobile-link>`](resources/views/components/mobile-link.blade.php) component.
- Profile page: photo, **QR code** (links to profile, degrades gracefully without GD), payment
  summary, **document upload** (Aadhaar/photo/agreement/other with expiry + e-sign flag) via
  [`StudentDocumentController`](app/Http/Controllers/Admin/StudentDocumentController.php), and a
  **bed-assignment history** table.
- Delete blocked while the student occupies a bed. Tests: [`StudentTest`](tests/Feature/StudentTest.php).

#### Module 3 details — Bed Assignment & History
- [`BedAssignmentService`](app/Services/BedAssignmentService.php) owns the lifecycle with
  row-locking + transactions: **assign** (flips bed → occupied, copies room rent, guards against
  double-allocation and a student holding two beds), **release** (frees bed → empty, keeps the
  history row, optionally marks the student *Left*), and **transfer** (close + reopen).
- [`AssignmentController`](app/Http/Controllers/Admin/AssignmentController.php): active-assignment
  list with a release modal (leave date + "mark left"), and an assign form with a Select2 student
  picker and floor→room→bed optgroup picker (rent auto-fills from the room).
- Per-bed **history** page ([`beds/{bed}/history`](resources/views/admin/beds/history.blade.php)):
  every past/current occupant with dates, duration, rent and **payments made during that stay**.
- The Bed Layout modal now deep-links **Assign** (empty beds) and **History** for any bed.
- Tests: [`BedAssignmentTest`](tests/Feature/BedAssignmentTest.php).

#### Module 4 details — Vacancy
- [`VacancyController`](app/Http/Controllers/Admin/VacancyController.php) +
  [vacancy page](resources/views/admin/vacancy/index.blade.php): summary cards
  (empty beds, leaving ≤ 7/15/30 days), an **empty/reserved beds** list with one-click
  *Assign*, and an **upcoming vacancies** list (active occupants leaving within 30 days,
  colour-coded by urgency). Filters by **floor / room / sharing**.

#### Module 5 details — Fees & Receipts (shared payment engine)
- [`PaymentService`](app/Services/PaymentService.php) is the single entry point for money
  received: generates a collision-free receipt number, writes the audit log, and (when a
  payable is passed) updates the obligation's `paid_amount` / `balance` / `status`. Semester
  Fees, Monthly Rent and AC Bills will all post through it.
- [`PaymentController`](app/Http/Controllers/Admin/PaymentController.php): list with date/mode
  filters + total, record form with **full/partial/advance** types and **cash/UPI/cheque/RTGS**
  modes (cheque/RTGS reference number conditionally required, validated in
  [`StorePaymentRequest`](app/Http/Requests/StorePaymentRequest.php)).
- Receipt: on-screen card with **Print**, **PDF** (dompdf, [receipt_pdf](resources/views/admin/payments/receipt_pdf.blade.php)),
  **WhatsApp** ([`WhatsAppService`](app/Services/WhatsAppService.php) — Cloud API when configured,
  else wa.me link), and **Email** (queued [`ReceiptMail`](app/Mail/ReceiptMail.php) with PDF attached).
- Tests: [`PaymentTest`](tests/Feature/PaymentTest.php).

#### Module 6 details — Semester Fees & Monthly Rent
- **Semester Fees** ([`SemesterFeeController`](app/Http/Controllers/Admin/SemesterFeeController.php)):
  per-student per-semester (1–8) dues with total/paid/balance/status, add/edit/delete, and a
  *Collect* modal that posts through [`PaymentService`](app/Services/PaymentService.php) so the
  balance and a receipt are produced in one step. One record per student+semester enforced.
- **Monthly Rent** ([`MonthlyRentController`](app/Http/Controllers/Admin/MonthlyRentController.php)):
  month picker + per-month rows for working professionals.
  [`MonthlyRentService`](app/Services/MonthlyRentService.php) **auto-generates** rows from each
  active working professional's bed-assignment rent (idempotent), exposed both as a *Generate*
  button and the scheduled command
  [`hsms:generate-monthly-rents`](app/Console/Commands/GenerateMonthlyRents.php) (1st of month).
- Shared [`collect_modal`](resources/views/admin/partials/collect_modal.blade.php) +
  [`CollectPaymentRequest`](app/Http/Requests/CollectPaymentRequest.php) (cheque/RTGS reference rule).
- Tests: [`MonthlyRentTest`](tests/Feature/MonthlyRentTest.php) (+ obligation settlement in PaymentTest).

#### Module 7 details — Payment Ledger
- [`LedgerService`](app/Services/LedgerService.php) aggregates each student's **billed / paid /
  outstanding** across semester fees, monthly rent and AC bills, plus a combined obligations list.
- [`LedgerController`](app/Http/Controllers/Admin/LedgerController.php): an all-students summary
  (with grand totals) and a per-student statement (obligations + full payment history).
- **Exports:** per-student **PDF** (dompdf) and **Excel** payment history, plus an all-students
  **Excel summary** ([`LedgerSummaryExport`](app/Exports/LedgerSummaryExport.php),
  [`PaymentHistoryExport`](app/Exports/PaymentHistoryExport.php), maatwebsite/excel).
- Tests: [`LedgerTest`](tests/Feature/LedgerTest.php).

#### Module 8 details — AC Bill Management
- [`AcBillService`](app/Services/AcBillService.php): builds a monthly bill for an **AC room** from
  meter readings — `(current − previous) × unit_price` — and splits it **equally among all**
  occupants or **among selected** students, pushing any rounding remainder onto the last share so
  the split always reconciles to the total. Previous reading auto-fills from the room's last bill;
  one bill per room+month enforced.
- [`AcBillController`](app/Http/Controllers/Admin/AcBillController.php): list with **AC income**
  (collected) + **AC due** summary, a generate form with **live unit/total/per-student calculation**,
  a bill detail with per-student shares, and *Collect* (posts through the payment engine →
  appears in the ledger automatically).
- Tests: [`AcBillTest`](tests/Feature/AcBillTest.php).

> 🎉 All four obligation types (semester fees, monthly rent, AC bills, ad-hoc fees) now flow
> through one payment engine and roll up into the ledger and dashboards.

#### Module 9 details — Reports
- [`ReportService`](app/Services/ReportService.php) builds normalised datasets
  (`headings` / `rows` / `money` columns / `total`) for: **Collection**
  (daily/weekly/monthly/yearly grouping), **Income by Mode**, **Occupancy** (floor-wise),
  **Pending Fees** (students with a balance), and **AC Bills** (income + due).
- [`ReportController`](app/Http/Controllers/Admin/ReportController.php) is a single dispatcher
  with a report landing page and a unified results view supporting **date range / grouping**
  filters and **Print / PDF / Excel** export ([`ReportExport`](app/Exports/ReportExport.php) is a
  generic FromArray exporter; PDF via dompdf).
- Tests: [`ReportTest`](tests/Feature/ReportTest.php).

#### Module 10 details — Notifications
- [`NotificationService`](app/Services/NotificationService.php) generates **deduplicated** alerts
  (refreshes the unread one for a subject instead of duplicating, and clears alerts when the
  condition resolves): subscription/renewal due, students leaving ≤7 days, pending fees, pending
  AC bills, document expiry ≤30 days. Super Admin gets the per-hostel renewal feed (`hostel_id` null).
- Scheduled command [`hsms:generate-notifications`](app/Console/Commands/GenerateNotifications.php)
  runs daily at 08:00.
- Live **topbar bell** (unread count + dropdown) via a view composer in
  [`AppServiceProvider`](app/Providers/AppServiceProvider.php), plus a full
  [notifications page](resources/views/notifications/index.blade.php) (mark read / mark all / delete).
  Feed scoping via `Notification::forUser()`.
- Tests: [`NotificationTest`](tests/Feature/NotificationTest.php).

#### Module 11 details — Global Search
- [`SearchController`](app/Http/Controllers/SearchController.php) powers the topbar box: hostel
  admins search **students (name/mobile), rooms and beds (number)** within their tenant; super
  admins search **hostels**. Returns grouped JSON with deep links.
- Debounced live dropdown rendered client-side ([`app.js`](resources/js/app.js) `initGlobalSearch`).
- Tests: [`SearchTest`](tests/Feature/SearchTest.php).

#### Module 12 details — Super Admin: Hostels, Subscriptions, Admins
- [`HostelService`](app/Services/HostelService.php) provisions a hostel + first subscription +
  an **auto-credentialed Hostel Admin login** (mobile = username, random password shown once),
  and `createSubscription()` renews + extends coverage and reactivates the hostel.
- [`HostelController`](app/Http/Controllers/SuperAdmin/HostelController.php) — full CRUD; the
  generated login is surfaced once via a [credentials banner](resources/views/superadmin/partials/credentials.blade.php).
- [`SubscriptionController`](app/Http/Controllers/SuperAdmin/SubscriptionController.php) — all
  subscriptions with paid/pending totals and an add/renew modal.
- [`AdminController`](app/Http/Controllers/SuperAdmin/AdminController.php) — add admin,
  enable/disable, reset password (new credentials shown once), **login history**, and a filterable
  **activity-log** feed.
- Tests: [`HostelProvisioningTest`](tests/Feature/HostelProvisioningTest.php).

> 🎉 Both portals are now fully functional. The super-admin search links from Module 11 resolve.

#### Add-on details — Expenses & Backups
- **Expense Management** ([`ExpenseController`](app/Http/Controllers/Admin/ExpenseController.php),
  `expenses` table): categorised expenses (electricity/water/staff salary/maintenance/…) with a
  date-range filter and a **Profit/Loss** summary (income vs expenses for the period).
  Test [`ExpenseTest`](tests/Feature/ExpenseTest.php).
- **Database Backups** ([`BackupService`](app/Services/BackupService.php),
  [`BackupController`](app/Http/Controllers/SuperAdmin/BackupController.php)): manual *Create
  Backup Now*, list/download/delete, path-traversal guarded, plus the scheduled
  [`hsms:backup`](app/Console/Commands/RunBackup.php) (nightly 02:00, prunes >30 days). Uses
  mysqldump (`DB_DUMP_BINARY` configurable for XAMPP/Hostinger).

#### Add-on details — Visitors & Complaints
- **Visitor Register** ([`VisitorController`](app/Http/Controllers/Admin/VisitorController.php),
  `visitors` table): check-in (optionally linked to a student) / check-out with an "inside now"
  count and date filter.
- **Complaints / Tickets** ([`ComplaintController`](app/Http/Controllers/Admin/ComplaintController.php),
  `complaints` table): log with category/priority, track status (open → in progress → resolved →
  closed) with a resolution note + `resolved_at` stamp.
- Test: [`VisitorComplaintTest`](tests/Feature/VisitorComplaintTest.php).

#### Add-on details — Multi-language (EN / HI / GU)
- [`SetLocale`](app/Http/Middleware/SetLocale.php) middleware (prepended to the web group) applies
  the session locale; [`LocaleController`](app/Http/Controllers/LocaleController.php) +
  `locale/{locale}` route store the choice; switchers in the topbar and on the login page.
- Translation files [`lang/hi.json`](lang/hi.json) & [`lang/gu.json`](lang/gu.json) (English = keys).
  Navigation, section headers, login and common UI run through `__()`; wrap further strings in
  `__()` to extend coverage. Locales configured in `config/app.php` `available_locales`.
- Test: [`LocaleTest`](tests/Feature/LocaleTest.php).

### ✅ Spec complete
All 12 core modules + add-ons (Expenses, Backups, Visitors, Complaints, Multi-language) are built.
Payment gateways were intentionally skipped per the project owner.

---

## 🧱 Tech stack
- **Backend:** Laravel 12, PHP 8.2+, MySQL 8, Sanctum, Queue & Scheduler
- **Frontend:** Bootstrap 5, DataTables, SweetAlert2, Chart.js, Select2 (bundled via Vite)
- **PDF / Excel:** barryvdh/laravel-dompdf, maatwebsite/excel
- **QR:** simplesoftwareio/simple-qrcode

## 🚀 Quick start (local — XAMPP)
See **[INSTALL.md](INSTALL.md)** for full steps. TL;DR:

```bash
composer install
npm install && npm run build
copy .env.example .env        # then set DB credentials
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

### Demo logins (after seeding)
| Role | Mobile | Password |
|------|--------|----------|
| Super Admin | `9999999999` | `ChangeMe@123` *(from `.env`)* |
| Hostel Admin | `9876543210` | `Password@123` |

## 🌐 Deploy to Hostinger
See **[DEPLOYMENT-HOSTINGER.md](DEPLOYMENT-HOSTINGER.md)**.

## 🏗 Architecture notes
- **Tenancy:** every hostel-owned model uses the `BelongsToHostel` trait, which adds a
  global `TenantScope` and auto-fills `hostel_id`. The active tenant is bound per-request
  by `SetTenant` middleware from the logged-in user; Super Admin stays unbound and sees all.
- **Auth:** username = mobile number (10 digits, normalised). Session-based web auth plus
  Sanctum for the PWA/API.
- **Audit:** mutating web requests + key domain events flow through `ActivityLogger`.
- **Config:** domain enums/options live in [`config/hsms.php`](config/hsms.php).
