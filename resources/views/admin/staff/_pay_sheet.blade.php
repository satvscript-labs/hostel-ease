{{-- ══ Pay Salary ══
     ONE sheet, included by both the Staff Board and the profile. There were two
     hand-maintained copies before, and they drifted: W6.2 pointed the profile's
     mode picker at the tenant's real payment_modes and the Board's copy kept
     its hardcoded cash/upi/bank — but paySalary() validates against
     payment_modes, where the default vocabulary is cash/upi/cheque/rtgs and
     'bank' does not exist. Choosing "Bank Transfer" from the Board therefore
     failed validation every single time (found W7.1).

     Scope contract — the including page's x-data must provide:
       payOpen              bool
       p                    { action, name, salary, amount }
       openPay(payload)     sets p (with amount defaulted to salary) + opens
       close()

     Salary month / paid-on are deliberately NOT reset between opens: paying a
     row of people for the same month is the normal case.

     W7.2 adds the payroll intelligence on top of this shell: reference numbers
     for modes that require them, the already-paid-this-month warning, and the
     month's attendance summary alongside the amount. --}}

<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="payOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
        <form method="POST" :action="p.action" data-ring-required
              class="custom-overlay-modal" :class="{ 'is-open': payOpen }" x-show="payOpen" x-transition.opacity @click.stop style="display: none;">
            @csrf
            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0">
                    <i class="fa-solid fa-money-bill-wave text-success"></i>
                    <span class="ms-1">{{ __('Pay Salary') }}</span>
                    <div class="fs-6 fw-normal text-muted mt-1" x-text="p.name"></div>
                </h5>
                <button type="button" class="btn-close" @click="close()"></button>
            </div>
            <div class="custom-overlay-body">
                <div class="row gx-3">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Salary Month') }} <span class="text-danger">*</span></label>
                        <input type="month" name="salary_month" class="form-control bg-light" value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Paid On') }} <span class="text-danger">*</span></label>
                        <input type="date" name="paid_on" class="form-control bg-light" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                            <input type="number" name="amount" x-model.number="p.amount" class="form-control bg-light fw-bold text-success" required min="1" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Payment Mode') }} <span class="text-danger">*</span></label>
                        {{-- The tenant's real modes. paySalary() validates
                             against this same table. --}}
                        <x-he-select name="mode" compact :submit="false"
                            :selected="$paymentModes->first()?->code"
                            :options="$paymentModes->mapWithKeys(fn ($m) => [$m->code => $m->name])->all()" />
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Notes') }}</label>
                    <input type="text" name="notes" class="form-control bg-light" maxlength="255" placeholder="{{ __('Optional note') }}">
                </div>
            </div>
            <div class="custom-overlay-footer bg-light">
                <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-success fw-semibold rounded-pill px-4 shadow-sm tactile-btn">
                    <i class="fa-solid fa-check me-2"></i>{{ __('Record Payment') }}
                </button>
            </div>
        </form>
    </div>
</template>
