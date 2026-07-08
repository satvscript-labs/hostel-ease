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
