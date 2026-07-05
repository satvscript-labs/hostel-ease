# HSMS — Mobile API Deployment

The Flutter Hostel Admin app talks to a new token API added to this Laravel
project. Deploy these files to Hostinger (`public_html/hsms`) for the
production app to work. **No database migrations are required** — the API uses
existing tables, and Sanctum's `personal_access_tokens` table was created during
the original install.

## Files to upload (full Hostel-Admin API)

Extract `hsms-api-full-deploy.zip` into `public_html/hsms` (choose **Replace** for
existing files). It contains:

**Controllers — the whole `app/Http/Controllers/Api/` directory (23 files):**
Auth, Dashboard, Notification, Search, Profile, Student, Payment, PaymentMode,
Complaint, Floor, Room, Bed, Vacancy, Assignment, Visitor, SemesterFee,
MonthlyRent, AcBill, Ledger, Expense, Promise, Report, and `Concerns/CollectsPayments`.

**Plus:**
```
app/Http/Middleware/ApiTenant.php   # stateless tenant + sets Auth user
bootstrap/app.php                   # registers the 'api.tenant' alias
routes/api.php                      # all /api/v1 routes (74)
app/Services/PaymentService.php     # lets the API pass collected_by
```

No database migrations are required — every endpoint uses existing tables.

## After uploading — clear caches

The new routes won't be visible until the route/config cache is rebuilt.
In Hostinger **File Manager**, delete these if they exist:

```
bootstrap/cache/config.php
bootstrap/cache/routes-v7.php
```

(They are regenerated automatically on the next request.)

## Verify it works (from any terminal)

Replace the mobile/password with a real Hostel Admin login.

```bash
# 1) Login → returns a token
curl -s -X POST https://hsms.satvscript.com/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"mobile":"9876543210","password":"YOURPASS","device_name":"test"}'

# 2) Use the token
curl -s https://hsms.satvscript.com/api/v1/dashboard \
  -H "Accept: application/json" -H "Authorization: Bearer PASTE_TOKEN_HERE"
```

A JSON dashboard response confirms the API is live.

## Endpoint reference

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v1/login` | Token login (hostel admin only) |
| GET  | `/api/v1/me` | Current user + branches |
| POST | `/api/v1/logout` | Revoke current token |
| GET  | `/api/v1/dashboard` | Stats + occupancy + 6-month collection |
| GET  | `/api/v1/students` | List (filters: `status`, `occupation`, `search`) |
| GET  | `/api/v1/students/{id}` | Detail + outstanding dues |
| GET  | `/api/v1/payment-modes` | Active modes + payment types |
| GET  | `/api/v1/payments` | Recent payments (filter: `student_id`) |
| POST | `/api/v1/payments` | Record a payment (optional `payable_type`/`payable_id`) |
| GET  | `/api/v1/complaints` | List + counts + options |
| POST | `/api/v1/complaints` | Log a complaint |
| PUT  | `/api/v1/complaints/{id}` | Update status |
| GET  | `/api/v1/notifications` | Alert feed + unread count |
| POST | `/api/v1/notifications/read-all` | Mark all read |
| POST | `/api/v1/notifications/{id}/read` | Mark one read |

**Auth headers:** `Authorization: Bearer <token>` on every guarded request.
**Branch:** optional `X-Hostel-Id: <id>` selects the active branch (defaults to
the admin's primary branch; validated against their accessible branches).
