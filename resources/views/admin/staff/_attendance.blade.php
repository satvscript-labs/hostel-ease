{{-- ══ Attendance tab ══
     EXTRACTED, NOT REBUILT (W7.1). This is the pre-design-system markup lifted
     out of index.blade.php verbatim so the Directory rebuild had one file to
     reason about. It is scheduled for its own rebuild in W7.3, together with
     the three real bugs it still carries:

       · unmarked staff default to "Present" and Save writes a row for
         EVERYONE — so opening a past date and hitting Save stamps the whole
         roster present for a day nobody reviewed;
       · future dates are accepted;
       · the month is hardcoded to the current one, with no way back.

     (The tenant hole — form-supplied staff ids written unchecked — is already
     fixed in the controller; it was too sharp to leave sitting.) --}}

<div class="card glass-tile rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold text-dark mb-1">{{ __('Daily Attendance') }}</h5>
            <div class="text-secondary small">{{ __('Mark presence, absence, or leaves for your staff.') }}</div>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="tab" value="attendance">
            <div class="bg-light rounded-pill px-3 py-2 d-flex align-items-center border border-light shadow-sm">
                <i class="fa-regular fa-calendar text-primary me-2"></i>
                <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm bg-transparent border-0 shadow-none p-0 fw-semibold" style="width: 130px;" onchange="this.form.requestSubmit()">
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <form method="POST" action="{{ route('admin.staff.attendance.save') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            @if($roster->isEmpty())
                <div class="empty-state py-5 text-center">
                    <i class="fa-solid fa-clipboard-check text-secondary mb-3 opacity-25" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark">{{ __('No active staff') }}</h5>
                    <div class="text-secondary">{{ __('Add active staff members to mark attendance.') }}</div>
                </div>
            @else
                <div class="d-flex flex-column">
                    @foreach($roster as $index => $s)
                        @php($cur = $marks[$s->id]->status ?? 'present')
                        <div class="p-4 border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 bg-white" style="animation: fadeUp 0.4s var(--ease-out-expo) {{ min($index * 0.05, 0.4) }}s both;">
                            <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                                <x-staff-avatar :staff="$s" size="48" />
                                <div style="min-width: 0;">
                                    <div class="fw-bold text-dark fs-5 mb-1 text-truncate">{{ $s->name }}</div>
                                    <div class="text-secondary small fw-bold text-uppercase letter-spacing-1 text-truncate">{{ $s->designation ?: __('Staff Member') }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="attendance-pill-group d-flex" role="group">
                                    @foreach([
                                        'present' => ['label' => __('Present'), 'color' => 'success', 'icon' => 'check'],
                                        'absent' => ['label' => __('Absent'), 'color' => 'danger', 'icon' => 'xmark'],
                                        'half_day' => ['label' => __('Half'), 'color' => 'warning', 'icon' => 'star-half-stroke'],
                                        'leave' => ['label' => __('Leave'), 'color' => 'secondary', 'icon' => 'calendar-minus'],
                                    ] as $val => $opt)
                                        <input type="radio" class="btn-check" name="status[{{ $s->id }}]" id="a{{ $s->id }}_{{ $val }}" value="{{ $val }}" @checked($cur === $val)>
                                        <label class="btn btn-sm px-3 attendance-pill att-{{ $opt['color'] }}" for="a{{ $s->id }}_{{ $val }}">
                                            <i class="fa-solid fa-{{ $opt['icon'] }} me-1 d-none d-sm-inline"></i> {{ $opt['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="p-4 bg-light d-flex justify-content-end border-top">
                    <button class="btn btn-dark fw-semibold rounded-pill px-5 py-2 shadow-sm tactile-btn"><i class="fa-solid fa-save me-2"></i> {{ __('Save Attendance') }}</button>
                </div>
            @endif
        </form>
    </div>
</div>
