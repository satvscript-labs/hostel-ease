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
