{{-- Vacant-bed picker (shared by Assign + Transfer). Searchable list; picking
     a bed sets the AC flag that drives the meter field. --}}
<div class="mb-3">
    <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Bed') }} <span class="text-danger">*</span></label>

    {{-- Selected --}}
    <template x-if="bed">
        <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-2" style="background:var(--he-primary-soft); border:1px solid rgba(79,70,229,.2);">
            <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:44px; height:44px; background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea));">
                <i class="fa-solid fa-bed"></i>
            </div>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-bold text-dark">{{ __('Room') }} <span x-text="bed.room"></span> · {{ __('Bed') }} <span x-text="bed.bed"></span>
                    <span class="badge rounded-pill ms-1" x-show="bed.is_ac" style="background:var(--he-warning-soft); color:#b45309; font-size:.62rem;"><i class="fa-solid fa-bolt"></i> AC</span>
                </div>
                <div class="small text-muted text-truncate"><span x-text="bed.floor"></span><span x-show="bed.sharing"> · <span x-text="bed.sharing"></span>-{{ __('sharing') }}</span></div>
            </div>
            <button type="button" class="btn btn-sm btn-white border rounded-pill px-3 fw-semibold flex-shrink-0" @click="bed = null">{{ __('Change') }}</button>
        </div>
    </template>

    {{-- Search + list --}}
    <div x-show="!bed">
        <div class="he-search he-search--inline mb-2">
            <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" x-model="bedSearch" class="he-search__input" placeholder="{{ __('Search room, bed or floor…') }}">
        </div>
        <div class="border rounded-3" style="max-height:210px; overflow-y:auto;">
            <template x-for="b in filteredBeds" :key="b.id">
                <button type="button" class="d-flex align-items-center gap-3 w-100 text-start border-0 bg-transparent px-3 py-2 acc-bed-row" @click="pickBed(b)">
                    <div class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px; height:36px; background:var(--he-bg-surface-raised); color:var(--he-text-muted);">
                        <i class="fa-solid fa-bed"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-semibold text-dark small">{{ __('Room') }} <span x-text="b.room"></span> · {{ __('Bed') }} <span x-text="b.bed"></span>
                            <span class="badge rounded-pill ms-1" x-show="b.is_ac" style="background:var(--he-warning-soft); color:#b45309; font-size:.58rem;">AC</span>
                        </div>
                        <div class="text-muted text-truncate" style="font-size:.72rem;"><span x-text="b.floor"></span></div>
                    </div>
                </button>
            </template>
            <div x-show="!filteredBeds.length" class="text-center text-muted small py-4">{{ __('No matching beds.') }}</div>
        </div>
    </div>
</div>
