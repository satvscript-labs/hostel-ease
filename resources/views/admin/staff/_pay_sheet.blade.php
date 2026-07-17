{{-- ══ Pay Salary ══
     ONE sheet, included by both the Staff Board and the staff profile.

     There were two hand-maintained copies before, and they drifted: W6.2
     pointed the profile's mode picker at the tenant's real payment_modes and
     the Board's copy kept its hardcoded cash/upi/bank — but paySalary()
     validates against payment_modes, where the default vocabulary is
     cash/upi/cheque/rtgs and 'bank' does not exist. Choosing "Bank Transfer"
     from the Board therefore failed validation every single time (found W7.1).

     Self-contained by design (W7.2): it owns its own Alpine scope and opens on
     a `pay-salary` window event, so a page only has to say

         @click="$dispatch('pay-salary', { action, name, salary, paid, attendance })"

     and knows nothing else about it. There is no scope for a page to get
     wrong, which is what let the two copies drift in the first place.

     Everything here REPORTS; nothing decides. The attendance summary and the
     already-paid notice exist so the OWNER can judge the amount — neither
     computes it, neither blocks the payment. There is no rule in this product
     saying a half-day is half a day's pay, and inventing one would be the
     system assuming a figure nobody asked for. --}}

<div x-data="staffPaySheet()" @pay-salary.window="openPay($event.detail)">
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="payOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" :action="p.action" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': payOpen }" x-show="payOpen" x-transition.opacity @click.stop
                  @he-select-change="if ($event.detail.name === 'mode') payMode = $event.detail.value" style="display: none;">
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
                            <input type="month" name="salary_month" x-model="salaryMonth" class="form-control bg-light" max="{{ now()->format('Y-m') }}" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Paid On') }} <span class="text-danger">*</span></label>
                            <input type="date" name="paid_on" class="form-control bg-light" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </div>
                    </div>

                    {{-- Already paid for this month — the same discipline as the
                         duplicate-deposit notice (W6.4): say it, don't block it. --}}
                    <div class="sal-warn mb-4" x-show="alreadyPaid > 0" x-cloak>
                        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                        <span>
                            <span x-text="'₹' + fmt(alreadyPaid)"></span>
                            {{ __('is already recorded for') }} <span x-text="monthLabel"></span>.
                            {{ __('Record another only if this is an advance, a correction, or a balance you held back.') }}
                        </span>
                    </div>

                    {{-- Shown only for months actually loaded (attWindow) —
                         "nobody marked attendance" and "we didn't fetch it" are
                         different facts and must not look the same. Outside the
                         window the sheet says nothing at all. --}}
                    <template x-if="attWindow.includes(salaryMonth)">
                        <div class="sal-att mb-4">
                            <div class="sal-att__head">
                                <i class="fa-solid fa-clipboard-user me-1"></i>
                                <span x-text="'{{ __('Attendance in') }} ' + monthLabel"></span>
                            </div>
                            <template x-if="monthAttendance">
                                <div class="sal-att__chips">
                                    <template x-for="c in attendanceChips" :key="c.key">
                                        <span class="sal-chip" :class="'sal-chip--' + c.key.replace('_', '-')">
                                            <b x-text="c.count"></b><span x-text="c.label"></span>
                                        </span>
                                    </template>
                                </div>
                            </template>
                            <template x-if="! monthAttendance">
                                <div class="small text-muted">{{ __('No attendance was marked this month.') }}</div>
                            </template>
                            <div class="sal-att__note">{{ __('For your information — the amount is yours to decide.') }}</div>
                        </div>
                    </template>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" x-model.number="p.amount" class="form-control bg-light fw-bold text-success" required min="1" step="0.01">
                            </div>
                            {{-- Their contracted salary is where the field STARTS;
                                 this only says what it was if you change it. --}}
                            <div class="form-text small" x-show="Number(p.amount) !== Number(p.salary)" x-cloak>
                                {{ __('Contracted:') }} <span x-text="'₹' + fmt(p.salary)"></span>
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

                    {{-- Appears only for modes that declare they need one
                         (cheque, RTGS). A cheque salary with no cheque number is
                         a payment the record cannot trace — the column and the
                         fillable existed all along and nothing ever collected
                         it (W7.2). Server enforces it regardless. --}}
                    <div class="mb-4" x-show="requiresReference" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Reference / Transaction ID') }} <span class="text-danger">*</span></label>
                        <input type="text" name="reference_number" class="form-control bg-light" maxlength="100"
                               :required="requiresReference" placeholder="{{ __('e.g. Cheque No., NEFT UTR') }}">
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
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('staffPaySheet', () => ({
        payOpen: false,
        p: { action: '', name: '', salary: 0, amount: 0, paid: {}, attendance: {} },

        // Deliberately NOT reset between opens: paying a row of people for the
        // same month is the normal case, and re-typing it each time is the kind
        // of small insult software shouldn't hand out.
        salaryMonth: @js(now()->format('Y-m')),

        payMode: @js($paymentModes->first()?->code),
        modeReq: {{ Illuminate\Support\Js::from($paymentModes->mapWithKeys(fn ($m) => [$m->code => (bool) $m->requires_reference])) }},
        attWindow: {{ Illuminate\Support\Js::from($payroll['window']) }},
        attLabels: @js([
            'present' => __('present'),
            'half_day' => __('half day'),
            'absent' => __('absent'),
            'leave' => __('leave'),
        ]),

        fmt(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

        get alreadyPaid() { return this.p.paid?.[this.salaryMonth] ?? 0; },

        get monthAttendance() { return this.p.attendance?.[this.salaryMonth] ?? null; },

        get attendanceChips() {
            const a = this.monthAttendance;
            if (! a) return [];

            // Fixed order, and only statuses that actually occurred — a row of
            // "0 absent · 0 leave" is noise pretending to be information.
            return Object.entries(this.attLabels)
                .filter(([key]) => a[key])
                .map(([key, label]) => ({ key, label, count: a[key] }));
        },

        get monthLabel() {
            if (! this.salaryMonth) return '';
            const [y, m] = this.salaryMonth.split('-');
            return new Date(Number(y), Number(m) - 1, 1)
                .toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
        },

        get requiresReference() { return !! this.modeReq[this.payMode]; },

        openPay(payload) {
            // Contracted salary is the STARTING point, fully editable — never a
            // derived figure.
            this.p = { ...payload, amount: payload.salary };
            this.payOpen = true;
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.payOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
