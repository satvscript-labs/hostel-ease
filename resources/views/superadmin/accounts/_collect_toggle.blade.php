{{-- S2 · the collection choice, shared by the Renew / Add to cycle / Align modals.

     Two EQUAL options, not a primary and a fallback. That is a deliberate product
     decision with a number behind it (06_RAZORPAY_CAPABILITIES.md §4): a ₹50,000
     yearly renewal taken as a bank transfer costs ₹0 in gateway fees, and the same
     amount through a payment link costs ₹1,180. Online collection is a convenience
     worth buying per customer, not the default to nudge everyone towards.

     Both choices post to the SAME route with the same quote — `collect` is the only
     difference — so the two can never price the same charge differently.

     Required: $model (the Alpine property holding the choice), $linksEnabled.
--}}
@if($linksEnabled)
    <input type="hidden" name="collect" :value="{{ $model }}">

    <div class="mb-4">
        <div class="form-label fw-bold small text-muted">COLLECTION</div>
        <div class="d-flex gap-2">
            <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn"
                    :class="{{ $model }} === 'offline' ? 'btn-primary' : 'btn-light border'"
                    @click="{{ $model }} = 'offline'">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i>Record as received
            </button>
            <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn"
                    :class="{{ $model }} === 'link' ? 'btn-primary' : 'btn-light border'"
                    @click="{{ $model }} = 'link'">
                <i class="fa-solid fa-link me-1"></i>Send a payment link
            </button>
        </div>
        <div class="form-text" x-show="{{ $model }} === 'link'" x-cloak>
            Records the charge as <strong>unpaid</strong> and creates a Razorpay link you can send.
            Coverage extends only once it is actually paid. Razorpay charges about 2% + GST, so a
            large yearly amount is cheaper taken by bank transfer and recorded here.
        </div>
    </div>
@else
    {{-- Razorpay is not configured, so there is nothing to choose between. Offline
         recording is a complete path on its own; a button that always errors is
         worse than no button. --}}
    <input type="hidden" name="collect" value="offline">
@endif
