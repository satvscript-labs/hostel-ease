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
