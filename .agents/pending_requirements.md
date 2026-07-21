# Pending Requirements

## 1. Mobile OTP Verification (Deferred)

**Status:** Pending — Waiting for API provider selection
**Target Users:** All Indian (local)
**Recommended Providers:** Fast2SMS (~₹0.20/SMS) or Textlocal

**Implementation Notes:**
- User enters mobile number during registration.
- Laravel generates 4-6 digit OTP, stores in cache with 5-min TTL.
- Fires HTTP request to SMS Gateway API.
- User inputs OTP, Laravel validates.
- Build a generic `SmsService` interface so provider can be swapped easily.

**Action Required:** User needs to finalize an SMS API provider and obtain API credentials before implementation can begin.

---

## 2. Image Compression — Cloud Storage Scalability (Future)

**Status:** Core compression implemented. Storage abstraction built for future R2/S3 migration.
**Action Required:** When ready to move to cloud storage (e.g., Cloudflare R2, AWS S3), update the `FILESYSTEM_DISK` env variable and configure the driver in `config/filesystems.php`.

---

## 3. Auto-Debit / Recurring Billing — Razorpay Subscriptions (Deferred, Phase 7)

**Status:** Deferred by design. The account-level billing model (subscription_accounts,
AccountBillingService, owner self-serve Renew-all) is built entirely on Razorpay's one-time
**Orders API** — every renewal today is a manually or owner-triggered single payment, never an
auto-charge. Two schema seams are already in place for this phase so it's additive later, not a
rewrite:

- `subscription_accounts.auto_debit` (boolean, default false)
- `subscription_accounts.razorpay_subscription_id` (nullable string)

**Why deferred (not a technical blocker, a product one):** UPI Autopay — the payment method most
Indian small-business owners would actually use for auto-debit — caps recurring auto-approved
charges at **₹15,000 per debit** without an extra pre-debit e-mandate authorization step. Our own
multi-branch customers (the ones this whole account-model rebuild was for) routinely owe **more**
than that in one renewal (5 branches × ₹10,000 = ₹50,000/yr). So auto-debit would currently create
*more* friction for exactly the accounts it's meant to help, not less. Revisit if/when a customer
base with consistently smaller per-renewal totals emerges, or if Razorpay changes the Autopay cap.

**What Razorpay auto-debit actually requires (different API family):**

- The **Subscriptions API** (`/v1/subscriptions`), not the Orders API `RazorpayService` already
  wraps. Needs: a Razorpay **Plan** (awkward here — our pricing is per-account/per-quantity, likely
  one plan per unit price with `quantity` = branch count), a **Subscription** against it, and the
  **authorization transaction** (the ₹0–₹1 mandate charge).
- New webhook events beyond today's `order.paid` / `payment.failed` / `refund.*`:
  `subscription.charged/activated/halted/cancelled/pending`.
- **Quantity changes mid-cycle** don't map cleanly onto a fixed Razorpay plan — plan-quantity update
  vs cancel-and-recreate (which re-prompts the customer to re-authorize). The main design question.
- Needs a UI opt-in (Account 360 / owner Subscription page), not a silent default.

**Action Required:** intentionally not built. When picked up: (1) confirm the ₹15k Autopay cap still
binds, (2) decide the quantity-change UX, (3) design the opt-in UI, (4) extend `RazorpayService` +
`WebhookController`.

---

## 4. AC Meter Reading Validation (DONE — 2026-07-18)

**Status: DONE.** A meter only counts up: every reading entry point (assign/release/transfer, AC
bill generate + edit) now floors against the room's last recorded reading, **derived** by
`AcMeterService` from bills (soft-deleted included — owner call) + move readings; no schema change
(owner call: Option A, kept scalable via the service seam). Below-floor warns **inline** with the
floor shown up front ("Last recorded: N") and reveals a "meter was reset / replaced" override —
never a popup on a normal reading; overrides are accepted and logged (`ac_meter.reset`). A bill's
start floors only against readings **before** its first month (mid-window move readings never floor
it — the math-critical case, tested). The Generate modal was also rebuilt (v2, after owner
feedback): a **From → To billing-period range** that prefills to "after the last bill → last month"
(never-billed rooms start at first occupancy), plus a **meter tape** with live per-month units/₹ and
instant below-previous red flags. Report: `_artifact/ac_meter_validation/00_report.md` +
`01_testing.md`. Suite 302 → 312. Deferred sub-cases (documented in the report): back-dated-event
bracket validation; the soft upper-sanity nudge.

---

## 5. Identity Documents / Aadhaar (DONE — private-disk P1–P5)

**Status: DONE (2026-07-18).** Uploads (staff/student/registration photos + Aadhaar cards) moved off
the public web root onto a **private disk**, served only through the authenticated, tenant-scoped
`SecureFileController` (P1–P4). The Aadhaar **number** is **encrypted at rest**, **masked** to last-4
everywhere, and revealed in full only through a **logged** endpoint (P5). Reports:
`_artifact/ui_ux_audit/05_private_disk_plan.md` + `09_testing_P5_aadhaar.md`.
**CAVEAT:** encrypted values need the current `APP_KEY` — don't rotate it without re-keying the data.

---

## 6. Reverse Transaction (Research Pending)

- {PS: i want you to Research this entire thing and i want a system that when we reverse a transaction it just simple should not ask confirmation and reverse the thing ... in some cases you would have collected money and might keep it even after reversing ... so we ask what most app asks, that is, if reverse to original payment methods... or transfer money to credit and keep money ... so i want you to Research this and give me your side of suggestions and way to tackle this thing. [after Research replace this point with your research and suggestions and all]}

---

## 7. Report Candidates Proposed But Not (Yet) Approved (W8, Jul-17)

**Status:** Owner undecided — "I don't know yet if we should go for it or not." Take up whenever
wanted; the W8 report-page skeleton makes each one a small standalone slice.

- **Front Desk report** — visitors per day + complaint resolution times (data exists).
- **Deposits custody ledger** — exportable held/refunded/deducted history.
- **Payroll report** — salary paid per month/staff vs contracted (W7.2 data).
- **Moves & Churn** — joins vs leaves per month from bed_assignments.

---

## 8. Aligned Row System — rollout (DONE — MF)

**Status: DONE (2026-07-18).** The law lives in `ui_design_guidelines.md §4.11`; rolled out across
every audit-era list in the MF pass — finance card-lists' wide-tier figure drift fixed with fixed
grid tracks (card look kept), and the two sideways-scrolling super-admin tables (Customers, Account
360 orders) rebuilt into subgrid lists. Discounts + hidden Subscriptions tables deferred by scope.
Report: `_artifact/ui_ux_audit/08_MF_aligned_rows.md`.

---

## 9. Minor UI Changes/Bugs

1. In mobile UI, use short relative-time forms ("45m ago", not "45 minutes ago"). — *pending*
2. ~~Tablet-view rows not visually aligned~~ — **DONE**, folded into the Aligned Row System rollout (§8 / §4.11).
3. Creating a new branch, adding hosted with pending payment mode still add records in Orders & payments...right now nothing gets there ... if i create one with pending still adds 1 year (2027) to subscription and when renew it takes only 10k but renews till 2028 means 2 years with everything paid up ...

---

## 10. Presence Module — Deferred Value-Adds & Future Phases

**Context:** the Presence / In-Out Register module (TimeWatch TrueFace1000EW gate device) is planned
in `_artifact/presence_module/` (plans 00–06). The owner approved its core build phases (P1–P6) on
2026-07-19; the items below were explicitly **deferred out of that build** and are picked up later.

- **Night auto-roll-call digest** (idea #9.8) — one WhatsApp/notification at curfew+30min to the
  warden listing who's still out ("3 still out: A (Room 204), B (301), C staff"). Rides the P5
  curfew infrastructure; deferred by owner.
- **Card fallback** (idea #9.9) — the device reads proximity cards; issue cards to residents whose
  face enrollment is flaky (same `AddUser` call, `card_number` already on `presence_profiles`).
  Deferred by owner.
- **Door-lock / access control** — the device *can* drive an electric lock; the module ships as a
  logger only, with the seam preserved (`01_module_plan.md §5.1`). Additive future phase.
- **Mobile API** (`/api/v1/presence/*`) — deferred; not focusing on the mobile apk now. Pipeline is
  API-ready (`04_integration_and_api.md §7`).
- **Out-too-long alert** (idea #9.5) + **Visitor gate passes** (idea #9.10, touches Front Desk) —
  ❓ not yet decided; parked in the plan's §9 for an owner call.

---

## 11. Public ID / ULID Hardening (Separate Workstream — approach approved 2026-07-19)

**Status:** approach agreed, awaiting owner's timing cue to start. Add an opaque `public_id` (ULID)
to URL-facing tables (students, staff, invoices, payments, registrations, complaints, expenses, AC
bills…) and point `getRouteKeyName()` at it; **primary keys stay untouched** (no FK churn). Fixes
sequential-ID enumeration in URLs. **Not a cross-tenant vulnerability** — `TenantScope` already
404s cross-hostel access; this is enumeration/BI-leak hardening. Decoupled from Presence; will get
its own plan + testing doc. Full analysis: `_artifact/presence_module/01_module_plan.md §8`.
