{{-- ══ Attendance ══ (W7.3 — old design scrapped entirely)

     What was wrong, beyond the looks: marking 10 people cost 11 taps EVERY DAY
     (ten pills plus Save) for a record nothing is billed on — and the pills
     defaulted to "Present", so the fast path silently wrote rows for people
     nobody had reviewed. It was slow and dishonest at the same time.

     The shape now follows how a hostel actually works: on a normal day everyone
     turns up, so the register's real content is who DIDN'T.

       · "Mark all present" fills the unmarked in one tap → flag the exceptions.
         Two taps, not eleven. It is an explicit action the owner chose, which
         is exactly what the old implicit default was not.
       · Auto-save. No Save button to forget, nothing lost by walking away.
       · Present/Absent is a big two-way toggle (~95% of marks, thumb-sized);
         Half day and Leave live one tap deeper in a shared sheet — so a phone
         row holds two wide targets instead of four cramped ones.
       · Unmarked is a REAL state: shown as "Not marked", never written.
       · The strip only goes up to today, so a future day isn't reachable.

     Neither bulk action ever overwrites a mark you already made — they fill
     blanks only. A decision you took is never silently undone by a shortcut. --}}

{{-- #att-panel is the SWAP TARGET and x-data sits INSIDE it, not on it: the
     fragment helper replaces a target's innerHTML, so an x-data on the target
     itself would survive the swap holding the OLD config — the rows would
     change and the component would still think it was on the previous week. --}}
<div id="att-panel">
<div x-data="attendanceBoard({{ Illuminate\Support\Js::from([
        'date' => $attendance['date'],
        'strip' => $attendance['strip'],
        'marks' => $attendance['marks'],
        'suggested' => $attendance['suggested'],
        'roster' => $attendance['roster']->map(fn ($s) => ['id' => (string) $s->id, 'name' => $s->name])->values(),
        'prev' => $attendance['prev'],
        'next' => $attendance['next'],
        'atToday' => $attendance['at_today'],
        'saveUrl' => route('admin.staff.attendance.save'),
    ]) }})">

    <div class="panel-card">
        {{-- ══ Day strip ══ --}}
        <div class="att-strip-wrap">
            <button type="button" class="att-nav" @click="goTo(cfg.prev)" :disabled="busy" aria-label="{{ __('Previous week') }}">
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <div class="att-strip">
                <template x-for="d in cfg.strip" :key="d.date">
                    <button type="button" class="att-day" :class="{ 'is-selected': date === d.date, 'is-today': d.is_today }"
                            @click="goTo(d.date)">
                        <span class="att-day__dow" x-text="d.dow"></span>
                        <span class="att-day__num" x-text="d.day"></span>
                        <span class="att-day__dot" :class="'is-' + dayState(d.date)"></span>
                    </button>
                </template>
            </div>

            <button type="button" class="att-nav" @click="goTo(cfg.next)" :disabled="busy || cfg.atToday" aria-label="{{ __('Next week') }}">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            {{-- Jump to any past date. Its own form so the panel swaps in place
                 (§4.3) rather than reloading the Staff Board. .he-datechip is
                 the canonical control — it already solves the two desktop traps
                 (browsers open the calendar only from the tiny indicator, and
                 non-WebKit needs showPicker()), which is the W6.2 "clicking
                 From/To does nothing" bug. --}}
            <form method="GET" action="{{ route('admin.staff.index') }}" x-ref="dateForm"
                  data-fragment="#att-panel" class="att-jump m-0">
                <input type="hidden" name="tab" value="attendance">
                <div class="he-datechip att-jump__chip" title="{{ __('Jump to a date') }}">
                    <span class="he-datechip__ic"><i class="fa-solid fa-calendar-day"></i></span>
                    <span class="he-datechip__txt">
                        <span class="he-datechip__lbl">{{ __('Jump to') }}</span>
                        <span class="fw-bold small">{{ __('Date') }}</span>
                    </span>
                    <input type="date" name="date" x-ref="dateInput" value="{{ $attendance['date'] }}"
                           max="{{ $attendance['today'] }}" aria-label="{{ __('Jump to a date') }}"
                           @click="$event.target.showPicker?.()" @change="jump()">
                </div>
            </form>
        </div>

        {{-- ══ Day header: what's done, what's left, and the shortcuts ══ --}}
        <div class="att-head">
            <div class="att-head__l">
                <div class="att-head__date" x-text="dayLabel"></div>
                <div class="att-head__sum">
                    <template x-if="marked === 0">
                        <span class="text-muted">{{ __('Nobody marked yet') }}</span>
                    </template>
                    <template x-if="marked > 0">
                        <span class="d-flex flex-wrap gap-1 align-items-center">
                            <template x-for="c in chips" :key="c.key">
                                <span class="sal-chip" :class="'sal-chip--' + c.key.replace('_', '-')">
                                    <b x-text="c.count"></b><span x-text="c.label"></span>
                                </span>
                            </template>
                            <template x-if="unmarked > 0">
                                <span class="sal-chip"><b x-text="unmarked"></b><span>{{ __('not marked') }}</span></span>
                            </template>
                        </span>
                    </template>
                </div>
            </div>

            <div class="att-head__r">
                {{-- Save state. There is no Save button; this is the whole
                     contract, so it has to be honest — including when it fails,
                     because a mark silently dropped is worse than no mark. --}}
                <span class="att-save" :class="{ 'is-failed': failed }">
                    <template x-if="failed">
                        <span class="d-inline-flex align-items-center gap-2">
                            <span><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('Not saved') }}</span>
                            <button type="button" class="btn btn-sm btn-white border rounded-pill px-2 py-0 fw-bold" @click="flush()">{{ __('Retry') }}</button>
                        </span>
                    </template>
                    <template x-if="! failed && busy">
                        <span><i class="fa-solid fa-circle-notch fa-spin me-1"></i>{{ __('Saving…') }}</span>
                    </template>
                    <template x-if="! failed && ! busy && savedOnce">
                        <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>{{ __('Saved') }}</span>
                    </template>
                </span>
            </div>
        </div>

        {{-- ══ Shortcuts ══ --}}
        <div class="att-tools" x-show="roster.length > 0">
            <template x-if="unmarked > 0">
                <div class="att-tools__row">
                    {{-- title carries the full sentence at every width, so the
                         dropped words are never actually lost. --}}
                    <button type="button" class="btn btn-sm btn-premium rounded-pill fw-bold px-2 px-sm-3 tactile-btn text-nowrap"
                            :title="bulkLabel" @click="markRemainingPresent()">
                        <i class="fa-solid fa-check-double me-1"></i>
                        <span class="att-tool__full" x-text="bulkLabel"></span>
                        <span class="att-tool__short" x-text="bulkLabelShort"></span>
                    </button>
                    <template x-if="suggestedUnmarked.length > 0">
                        <button type="button" class="btn btn-sm btn-white border rounded-pill fw-bold px-2 px-sm-3 tactile-btn text-nowrap"
                                :title="'{{ __('Confirm gate presence for') }} ' + suggestedUnmarked.length + ' {{ __('staff') }}'" @click="confirmSuggested()">
                            <i class="fa-solid fa-door-open me-1 text-primary"></i>
                            <span class="att-tool__full">{{ __('Confirm gate') }} (<span x-text="suggestedUnmarked.length"></span>)</span>
                            <span class="att-tool__short"><i class="fa-solid fa-door-open"></i> <span x-text="suggestedUnmarked.length"></span></span>
                        </button>
                    </template>
                    <template x-if="copySource">
                        <button type="button" class="btn btn-sm btn-white border rounded-pill fw-bold px-2 px-sm-3 tactile-btn text-nowrap"
                                :title="'{{ __('Copy the marks from') }} ' + copySourceLabel" @click="copyPreviousDay()">
                            <i class="fa-solid fa-copy me-1"></i>
                            <span class="att-tool__full" x-text="'{{ __('Copy') }} ' + copySourceLabel"></span>
                            <span class="att-tool__short" x-text="copySourceLabel"></span>
                        </button>
                    </template>
                </div>
            </template>
            <template x-if="unmarked === 0">
                <div class="att-tools__done">
                    <i class="fa-solid fa-circle-check me-1"></i>
                    <span x-text="'{{ __('All') }} ' + roster.length + ' {{ __('marked') }}'"></span>
                </div>
            </template>
        </div>

        {{-- ══ Roster ══ --}}
        <div class="he-adaptive">
            @forelse($attendance['roster'] as $s)
                @php $sid = (string) $s->id; @endphp
                <div class="att-row" :class="{ 'is-unmarked': ! statusOf(@js($sid)) }">
                    <div class="att-row__who">
                        <x-staff-avatar :staff="$s" size="40" />
                        <div style="min-width: 0;">
                            <div class="fw-bold text-dark text-truncate">{{ $s->name }}</div>
                            <div class="att-row__sub text-truncate">
                                <span x-show="! statusOf(@js($sid)) && ! isSuggested(@js($sid))" class="text-muted">{{ __('Not marked') }}</span>
                                <span x-show="! statusOf(@js($sid)) && isSuggested(@js($sid))" x-cloak
                                      class="att-gate-hint" title="{{ __('Seen at the gate today — confirm to mark present') }}">
                                    <i class="fa-solid fa-door-open"></i>{{ __('Seen at gate') }}
                                </span>
                                <span x-show="statusOf(@js($sid))" x-cloak
                                      :class="'att-tag att-tag--' + (statusOf(@js($sid)) || '').replace('_', '-')"
                                      x-text="labelFor(statusOf(@js($sid)))"></span>
                            </div>
                        </div>
                    </div>

                    <div class="att-row__act">
                        <div class="att-toggle" role="group" aria-label="{{ __('Attendance for :name', ['name' => $s->name]) }}">
                            <button type="button" class="att-seg att-seg--present"
                                    :class="{ 'is-on': statusOf(@js($sid)) === 'present' }"
                                    :aria-pressed="statusOf(@js($sid)) === 'present'"
                                    @click="toggle(@js($sid), 'present')">
                                <i class="fa-solid fa-check"></i><span>{{ __('Present') }}</span>
                            </button>
                            <button type="button" class="att-seg att-seg--absent"
                                    :class="{ 'is-on': statusOf(@js($sid)) === 'absent' }"
                                    :aria-pressed="statusOf(@js($sid)) === 'absent'"
                                    @click="toggle(@js($sid), 'absent')">
                                <i class="fa-solid fa-xmark"></i><span>{{ __('Absent') }}</span>
                            </button>
                        </div>

                        {{-- Becomes the status itself when it's half day / leave,
                             so the row always states the truth rather than
                             hiding it behind three dots. --}}
                        <button type="button" class="att-more"
                                :class="{ 'is-set': isSecondary(@js($sid)) }"
                                @click="openSheet(@js($sid), @js($s->name))"
                                :aria-label="'{{ __('More options for') }} ' + @js($s->name)">
                            <template x-if="isSecondary(@js($sid))">
                                <span x-text="labelFor(statusOf(@js($sid)))"></span>
                            </template>
                            <template x-if="! isSecondary(@js($sid))">
                                <i class="fa-solid fa-ellipsis"></i>
                            </template>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-4">
                    <x-he-empty-state icon="clipboard-user" title="{{ __('No active staff') }}"
                        subtitle="{{ __('Add staff, or mark someone active, to take attendance.') }}" />
                </div>
            @endforelse
        </div>
    </div>

    @once
    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('attendanceBoard', (cfg) => ({
            cfg,
            date: cfg.date,
            marks: cfg.marks,        // { 'Y-m-d': { staffId: status } }
            roster: cfg.roster,      // [{ id, name }]
            suggested: cfg.suggested ?? {}, // { 'Y-m-d': { staffId: true } } — seen at the gate

            // Pending marks, and the day they belong to. The date is held WITH
            // the queue on purpose: the payload carries no date of its own, so
            // switching days with taps still pending would post them against the
            // wrong day. Every day-change flushes first.
            queue: {},
            queueDate: null,
            timer: null,

            busy: false,
            failed: false,
            savedOnce: false,

            sheetOpen: false,
            sheetId: null,
            sheetName: '',

            labels: @js([
                'present' => __('present'),
                'half_day' => __('half day'),
                'absent' => __('absent'),
                'leave' => __('leave'),
            ]),

            statusOptions: @js([
                ['key' => 'present', 'label' => __('Present'), 'icon' => 'check'],
                ['key' => 'absent', 'label' => __('Absent'), 'icon' => 'xmark'],
                ['key' => 'half_day', 'label' => __('Half day'), 'icon' => 'star-half-stroke'],
                ['key' => 'leave', 'label' => __('Leave'), 'icon' => 'calendar-minus'],
            ]),

            init() {
                // A phone user switching apps, or anyone closing the tab, must
                // not lose marks that are still sitting in the debounce window.
                const bail = () => this.flush(true);
                window.addEventListener('pagehide', bail);
                document.addEventListener('visibilitychange', () => document.hidden && bail());
            },

            // ── Reading ──
            statusOf(id) { return this.marks[this.date]?.[id] ?? null; },
            labelFor(s) { return s ? this.labels[s] : ''; },
            isSecondary(id) {
                const s = this.statusOf(id);
                return s === 'half_day' || s === 'leave';
            },
            get dayLabel() {
                const d = this.cfg.strip.find((x) => x.date === this.date);
                if (d?.is_today) return @js(__('Today'));
                return new Date(this.date + 'T00:00:00')
                    .toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
            },
            get marked() { return this.roster.filter((s) => this.statusOf(s.id)).length; },
            get unmarked() { return this.roster.length - this.marked; },

            // Presence bridge (P5): "seen at the gate that day" — a suggestion the
            // admin CONFIRMS, never an auto-write to payroll.
            isSuggested(id) { return !! (this.suggested[this.date] || {})[id]; },
            get suggestedUnmarked() { return this.roster.filter((s) => this.isSuggested(s.id) && ! this.statusOf(s.id)); },
            confirmSuggested() { this.suggestedUnmarked.forEach((s) => this.set(s.id, 'present')); },

            // Two labels, not one truncated one (§4.8: fit or drop — never wrap,
            // never half-clip). The count survives into the short form because
            // it's the part that says how many people this is about; only the
            // wording is dropped, and the title attribute still carries it.
            get bulkLabel() {
                return this.marked === 0
                    ? @js(__('Mark all present'))
                    : @js(__('Mark remaining present')) + ' (' + this.unmarked + ')';
            },
            get bulkLabelShort() {
                return this.marked === 0
                    ? @js(__('All present'))
                    : @js(__('All present')) + ' (' + this.unmarked + ')';
            },
            get chips() {
                const day = this.marks[this.date] ?? {};
                return Object.entries(this.labels)
                    .map(([key, label]) => ({ key, label, count: this.roster.filter((s) => day[s.id] === key).length }))
                    .filter((c) => c.count > 0);
            },

            // A day's dot counts only CURRENT roster members: a past day may hold
            // marks for people since deactivated, and counting those would show
            // "all marked" on a day that isn't.
            dayState(d) {
                const day = this.marks[d] ?? {};
                const n = this.roster.filter((s) => day[s.id]).length;
                if (n === 0) return 'none';
                return n >= this.roster.length ? 'all' : 'partial';
            },

            // ── The copy source: the most recent EARLIER day that has marks.
            // Not "yesterday" — yesterday may be a day nobody worked, and
            // copying nothing is a button that does nothing.
            get copySource() {
                if (this.unmarked === 0) return null;
                return Object.keys(this.marks)
                    .filter((d) => d < this.date && this.roster.some((s) => this.marks[d]?.[s.id]))
                    .sort()
                    .pop() ?? null;
            },
            get copySourceLabel() {
                if (! this.copySource) return '';
                return new Date(this.copySource + 'T00:00:00')
                    .toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric' });
            },

            // ── Writing ──
            setLocal(id, status) {
                if (! this.marks[this.date]) this.marks[this.date] = {};
                if (status === null) delete this.marks[this.date][id];
                else this.marks[this.date][id] = status;
            },

            set(id, status) {
                if (this.statusOf(id) === status) return;
                this.setLocal(id, status);
                this.enqueue(id, status);
            },

            // Tapping the segment that's already on clears it — the same gesture
            // undoes itself, which is why an individual mark needs no undo toast.
            toggle(id, status) {
                this.set(id, this.statusOf(id) === status ? null : status);
            },

            enqueue(id, status) {
                this.queueDate = this.date;
                this.queue[id] = status;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.flush(), 600);
            },

            // ── Bulk. Fills BLANKS only — a shortcut never silently overwrites
            // a decision you already made.
            markRemainingPresent() {
                const targets = this.roster.filter((s) => ! this.statusOf(s.id));
                if (! targets.length) return;
                targets.forEach((s) => { this.setLocal(s.id, 'present'); this.enqueue(s.id, 'present'); });
                this.undoToast(
                    targets.length + ' ' + @js(__('marked present')),
                    targets.map((s) => s.id)
                );
            },

            copyPreviousDay() {
                const src = this.copySource;
                if (! src) return;
                const changed = [];
                this.roster.forEach((s) => {
                    const v = this.marks[src]?.[s.id];
                    if (! v || this.statusOf(s.id)) return;
                    this.setLocal(s.id, v);
                    this.enqueue(s.id, v);
                    changed.push(s.id);
                });
                if (! changed.length) return;
                this.undoToast(changed.length + ' ' + @js(__('copied')), changed);
            },

            // One toast per BULK action only. A toast per tap would be noise —
            // and an individual tap already undoes itself.
            undoToast(text, ids) {
                window.Swal?.fire({
                    toast: true, position: 'top-end', icon: 'success', title: text,
                    showConfirmButton: true, confirmButtonText: @js(__('Undo')),
                    timer: 6000, timerProgressBar: true,
                }).then((r) => {
                    if (! r.isConfirmed) return;
                    ids.forEach((id) => { this.setLocal(id, null); this.enqueue(id, null); });
                });
            },

            // ── Saving ──
            async flush(sync = false) {
                clearTimeout(this.timer);
                if (! Object.keys(this.queue).length) return;

                const batch = this.queue;
                const date = this.queueDate;
                this.queue = {};
                this.busy = true;
                this.failed = false;

                const body = JSON.stringify({ date, marks: batch });

                // Leaving the page: fetch() is cancelled on unload, sendBeacon
                // is not. It can't carry a CSRF header, so the token rides in
                // the body (VerifyCsrfToken reads _token too).
                if (sync && navigator.sendBeacon) {
                    navigator.sendBeacon(
                        this.cfg.saveUrl,
                        new Blob([JSON.stringify({ date, marks: batch, _token: this.csrf })], { type: 'application/json' })
                    );
                    this.busy = false;
                    return;
                }

                try {
                    const res = await fetch(this.cfg.saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    });
                    if (! res.ok) throw new Error(res.status);
                    this.savedOnce = true;
                } catch (e) {
                    // Put the batch BACK rather than dropping it: a mark that
                    // vanishes silently is the one thing this page must never do.
                    // Newer entries win over the replayed ones.
                    this.queue = { ...batch, ...this.queue };
                    this.queueDate = date;
                    this.failed = true;
                } finally {
                    this.busy = false;
                }
            },

            get csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; },

            // ── Navigation ──
            async goTo(d) {
                if (d === this.date) return;
                await this.flush(); // the queue belongs to the day we're leaving

                // In the loaded window → instant, no round-trip. Outside it →
                // swap the panel in place (§4.3), never a full reload.
                if (this.cfg.strip.some((x) => x.date === d)) {
                    this.date = d;
                    return;
                }
                this.$refs.dateInput.value = d;
                this.$refs.dateForm.requestSubmit();
            },

            async jump() {
                await this.flush();
                this.$refs.dateForm.requestSubmit();
            },

            // ── Sheet ──
            openSheet(id, name) {
                this.sheetId = id;
                this.sheetName = name;
                this.sheetOpen = true;
                document.body.style.overflow = 'hidden';
            },
            closeSheet() {
                this.sheetOpen = false;
                document.body.style.overflow = '';
            },
        }));
    });
    </script>
    @endpush
    @endonce

    {{-- ══ Status sheet: ONE shared sheet, not a dropdown per row ══
         A per-row menu would need §4.2 stacking work and §4.7 placement on every
         single row, and would still land four small targets on a phone. One
         teleported sheet has neither problem and gives every status a full-width
         thumb target. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="sheetOpen" x-transition.opacity @click="closeSheet()" x-cloak style="display: none;">
            <div class="custom-overlay-modal att-sheet" :class="{ 'is-open': sheetOpen }" x-show="sheetOpen" x-transition.opacity @click.stop style="display: none;">
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-clipboard-user" style="color: var(--he-primary);"></i>
                        <span class="ms-1" x-text="sheetName"></span>
                        <div class="fs-6 fw-normal text-muted mt-1" x-text="dayLabel"></div>
                    </h5>
                    <button type="button" class="btn-close" @click="closeSheet()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="d-flex flex-column gap-2">
                        <template x-for="opt in statusOptions" :key="opt.key">
                            <button type="button" class="att-opt" :class="[ 'att-opt--' + opt.key.replace('_','-'), statusOf(sheetId) === opt.key ? 'is-on' : '' ]"
                                    @click="set(sheetId, opt.key); closeSheet()">
                                <i class="fa-solid" :class="'fa-' + opt.icon"></i>
                                <span class="fw-bold" x-text="opt.label"></span>
                                <i class="fa-solid fa-check ms-auto" x-show="statusOf(sheetId) === opt.key" x-cloak></i>
                            </button>
                        </template>
                        <button type="button" class="att-opt att-opt--clear" x-show="statusOf(sheetId)" x-cloak
                                @click="set(sheetId, null); closeSheet()">
                            <i class="fa-solid fa-eraser"></i>
                            <span class="fw-bold">{{ __('Clear mark') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
</div>{{-- /#att-panel --}}
