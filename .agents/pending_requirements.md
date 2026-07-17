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
  wraps (`createOrder`, `fetchPayment`, `verifySignature`, `verifyWebhook`). New service methods
  needed: create a Razorpay **Plan** (id, period, amount — awkward here since our pricing is
  per-account/per-quantity, not a fixed plan; likely one plan per unit price, with `quantity` =
  branch count passed at subscription-creation time), create a **Subscription** against that plan
  for a customer, and handle the **authorization transaction** (the small ₹0–₹1 charge used to
  register the mandate, e.g. via UPI Autopay/e-mandate/card network tokenization).
- New webhook events beyond today's `order.paid` / `payment.failed` / `refund.*`:
  `subscription.charged`, `subscription.activated`, `subscription.halted` (auto-debit failed N
  times), `subscription.cancelled`, `subscription.pending` (mandate needs re-authorization).
- **Quantity changes mid-cycle** (a customer adds/removes a branch) don't map cleanly onto a fixed
  Razorpay Subscription plan — needs either a plan-quantity update call or cancel-and-recreate,
  which resets the mandate and re-prompts the customer to re-authorize. This is the main design
  question to resolve, not just an API integration detail.
- Needs a UI opt-in (mentioned in `_artifact/subscription_update/00_MASTER_BRD.md` D2 "Hybrid" —
  manual/Orders now, auto-debit opt-in later) — a toggle on Account 360 and/or the owner
  Subscription page, not a silent default.

**Action Required:** No customer-facing action needed yet — this is intentionally not built. When
picked up: (1) confirm the ₹15k Autopay cap is still the binding constraint (Razorpay policy may
change), (2) decide the quantity-change UX (re-mandate vs. a different billing shape for auto-debit
accounts), (3) design the opt-in UI, (4) extend `RazorpayService` + `WebhookController` for the
Subscriptions API event set above.

---

## 4. AC Meter Reading Validation (Deferred — analysis done, safe to build later)

**Status:** Analysed W6.4. The current AC flow works without it — meter fields on
assign/release/transfer accept any number ≥ 0, which is *safe* (the split's own rails ignore
readings that contradict the bill) but *unguarded* (a warden can typo a reading lower than the
last one and only find out at billing time). This adds a front-line guard so bad readings are
caught at entry.

**The requirement (owner):** a meter reading entered for a room can't be physically lower than
what that room's meter last showed. "Assign a student to Room 202; if the last billed unit was
200, the AC-Meter-Now reading can't be lower than 200." The meter is monotonic — it only counts up.

### Do we need a new table? NO. (This is the key finding.)

Every reading we'd validate against **already exists** in current columns (DB scanned W6.4):

| Source | Columns | What it gives |
|---|---|---|
| `ac_bills` | `current_reading`, `previous_reading`, `bill_month`, `created_at`, `room_id` | The meter at each past billing. Latest bill's `current_reading` = last *billed* reading. |
| `bed_assignments` | `join_meter_reading`, `leave_meter_reading`, `join_date`, `leave_date` (bed → room) | The meter at each occupancy change (added W6.3). |

So a room's **last known reading** is derivable today:

```text
lastReading(room) = MAX(
    latest non-deleted ac_bills.current_reading  WHERE room_id = room,
    all bed_assignments.join_meter_reading / leave_meter_reading  WHERE bed.room_id = room
)
```

No schema change strictly required. *Optional* optimisation: a denormalised
`rooms.last_meter_reading` (kept current on every bill/assignment write) turns the MAX-of-two-queries
into an O(1) read — worth it only if the query ever shows up as slow, which at hostel scale it won't.
Recommend deriving, not denormalising, unless profiling says otherwise.

### Proposed shape (one small service, no new tables)

- **`App\Services\AcMeterService`**
  - `lastReading(Room $room, ?Carbon $asOf = null): ?float` — the highest reading recorded for the
    room strictly before `$asOf` (defaults to now). Reused everywhere a reading is validated,
    displayed, or pre-filled. Returns null for a room that's never had one.
  - `assertValid(Room $room, float $reading, Carbon $date): void` — throws a friendly
    `ValidationException` when `$reading` is below `lastReading($room, $date)`.
- **Wire-in points** (all already load the room; each is a one-line call):
  - `PropertyController::requireMeterReading()` → becomes `validateMeterReading()`, adding the
    floor check to the existing "required for AC" check. Covers assign, release, both legs of
    transfer.
  - `AcBillController::validateBillInput()` — already checks month-to-month monotonicity within one
    generation batch; extend it to also floor the first month's `previous_reading` against
    `lastReading()`, so a bill can't start below the last recorded meter.
  - The modals: show the floor inline ("last recorded: 200") and set the input's `min`, so it's
    guided, not just rejected — same discipline as the AC bill picker, which already pre-fills
    `last_reading`.

### Validation rules worth having (ranked)

1. **Monotonic floor (the core one, owner's example):** reading ≥ `lastReading(room, date)`. Highest
   value; catches the typo that matters.
2. **Release/transfer ≥ that student's own join reading:** a leaver can't have consumed negative
   units. Trivial, already have `join_meter_reading` on the row.
3. **Upper-sanity nudge (soft):** a reading absurdly higher than the last (e.g. >10× the typical
   monthly delta) is *probably* a typo — warn, don't block. Consumption is spiky; a hard cap would
   fight legitimate high-usage months.
4. **Back-dated events (edge case):** if an occupancy change is recorded with a *past* date, the
   reading must fit *between* the readings bracketing that date, not just above the latest. More
   complex; most moves are "now", so defer this sub-case.

### Open questions (to decide when picked up — NOT asking now)

- **Soft-deleted bills:** a reversed/deleted `ac_bill` recorded a reading the meter physically
  reached. Does its reading still floor future entries (meter moved) or vanish with the bill
  (treat as never-happened)? Leaning "still floors" — the physical meter doesn't un-move — but
  it's a judgment call.
- **Hard block vs. override:** should a below-floor reading be impossible, or blockable-with-a-reason
  (meter replaced, misread last time)? A meter *can* legitimately reset (hardware swap). Recommend
  hard-block by default with an explicit "meter was reset/replaced" override that records why.
- **Rule #3's threshold:** what multiple of the last delta counts as "probably a typo"?

**Action Required:** None now — the AC flow ships without it. When picked up: confirm the three
open questions above, then build `AcMeterService` + wire the four call-sites. Additive, no schema
change, no migration, no data backfill.

## 5. Identity Documents Are On A Public Disk (Deferred by decision — W7.1, own phase)

**Status:** Raised and deferred in W7.1 with the owner. Not a W7 regression — it is how the app has
always stored documents — but staff Aadhaar is the most sensitive data in the product, so it is
recorded here rather than left as folklore.

**What's actually wrong.** Staff photos and Aadhaar cards are written to the **`public` disk**
(`StaffController::storeImage` → `StorageService::store(..., 'public', ...)`), and student documents
go the same way (`StudentDocumentController::store`). That is not a folder name — it is a
behaviour: the file is served straight off the web server at `/storage/...`, so **none of the app's
guards ever run**. SetTenant, `role:`/`access:` middleware, route-model binding, the TenantScope —
all of it is bypassed, because the request never reaches PHP. Anyone holding the URL can open the
file, logged in or not, tenant or not. The Aadhaar *number* is also stored as plain text in
`staff.aadhaar_number`.

The realistic exposure is not filename brute force (the names are random enough). It is that **the
URL is the only credential, and URLs leak**: pasted into WhatsApp, left in browser history on a
shared front-desk PC, cached by a proxy, sent in a `Referer` header to a third party, harvested by
a browser extension. Once out, the link works forever, for anyone, and cannot be revoked — deleting
the file is the only remedy, and nothing is logged, because the app never saw the request.

**The fix (its own phase — deliberately NOT folded into a UI rebuild):**

1. Move both `staff/*` and `students/*` to a **private** disk (outside the web root).
2. Add an authenticated streaming route per document type — resolve the model (tenant scope applies),
   authorize, then `Storage::disk('private')->response($path)`. The controller becomes the guard.
3. Data migration: physically relocate existing files and rewrite the stored paths. Not reversible
   by hand — needs a real migration + a dry-run.
4. Replace every `Storage::disk('public')->url(...)` call in the views with the new route (staff
   profile, student documents).
5. Consider encrypting `aadhaar_number` at rest (`encrypted` cast) and masking it in the UI
   (`XXXX XXXX 9012`) — a number displayed in full to every sub-user is its own exposure.

**Why deferred (owner agreed):** fixing staff alone leaves the larger student pile exposed while
feeling done, and a file-relocation migration tangled into a page rebuild means debugging both at
once if either half breaks. It wants its own phase and its own testing.

**Worth stating plainly:** India's DPDP Act 2023 treats Aadhaar as sensitive personal data. This is
*defer and schedule*, not *ignore*.

**Action Required:** schedule as its own phase. No decisions blocked — the shape above is settled;
only step 5 (encrypt/mask) is a judgment call worth confirming when picked up.

**Full plan written 2026-07-17 → `_artifact/ui_ux_audit/05_private_disk_plan.md`** (phases P1–P5,
complete verified inventory, the migration command's copy-verify-rewrite design, tests, and the five
open decisions). Three findings there change the shape and are worth knowing even if this never gets
picked up:

- The **public registration form marks an Aadhaar image `required`, stores it on a public URL, and
  nothing in the app ever reads it** — `approve()` copies only `photo`. Maximum liability, zero
  benefit. Fix it or stop collecting it; both beat today.
- `approve()` **shares one photo path across two rows** (`student_registrations.photo` and
  `students.photo`), so any path rewrite must key on distinct paths, not rows. (It also means
  deleting a student's photo silently breaks the registration's — live today, unrelated.)
- `config/filesystems.php` still carries a `links` entry mapping `public_path('storage')` →
  `storage_path('app/public')`, while the `public` disk's root **is** `public_path('storage')`.
  `php artisan storage:link --force` would replace that real directory — holding every upload — with
  a symlink and orphan the lot. Inert only because nobody has run it. **Delete the entry regardless
  of whether this phase happens.**

---

## 6. Reverse Transaction (Research Pending)

- {PS: i want you to Research this entire thing and i want a system that when we reverse a transaction it just simple should not ask confirmation and reverse the thing ... in some cases you would have collected money and might keep it even after reversing ... so we ask what most app asks, that is, if reverse to original payment methods... or transfer money to credit and keep money ... so i want you to Research this and give me your side of suggestions and way to tackle this thing. [after Research replace this point with your research and suggestions and all]}

## 7. Minor UI Changes

1. in mobile ui, only use short forms rather then full "45 minutes ago"
2. fix mobile ui, raws in tablet view is not a good UI, they are inconsistent, elements are not aligned (might be aligned coding wise but atleast in terms of view they are not aligned)
