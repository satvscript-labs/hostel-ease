@extends('layouts.app')
@section('title', __('Change Password'))

@push('styles')
<style>
    .panel-card { background:#fff; border:1px solid rgba(0,0,0,0.05); border-radius:1.1rem; }
    .pw-hero { display:flex; align-items:center; gap:1rem; padding:1.5rem; background:var(--he-gradient-mesh, linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%)); border-radius:1.1rem 1.1rem 0 0; color:#fff; position:relative; overflow:hidden; }
    .pw-hero::after { content:''; position:absolute; top:-40px; right:-30px; width:160px; height:160px; background:radial-gradient(circle, rgba(147,51,234,.4) 0%, transparent 70%); border-radius:50%; filter:blur(18px); pointer-events:none; }
    .pw-avatar { width:52px; height:52px; border-radius:15px; background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea)); display:flex; align-items:center; justify-content:center; font-size:1.25rem; font-weight:800; color:#fff; box-shadow:0 8px 20px rgba(79,70,229,.35); flex-shrink:0; z-index:1; }
    .pw-field-wrap { position:relative; }
    .pw-toggle { position:absolute; top:50%; right:.65rem; transform:translateY(-50%); border:0; background:transparent; color:var(--he-text-muted,#64748b); width:32px; height:32px; border-radius:50%; }
    .pw-toggle:hover { background:var(--he-bg-surface-raised,#f1f5f9); color:var(--he-primary,#4f46e5); }
    .pw-meter { height:6px; border-radius:9999px; background:var(--he-bg-surface-raised,#e2e8f0); overflow:hidden; }
    .pw-meter-bar { height:100%; width:0; border-radius:inherit; transition:width .35s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)), background-color .35s; }
    .pw-rule { display:flex; align-items:center; gap:.45rem; font-size:.78rem; color:var(--he-text-muted,#64748b); transition:color .2s; }
    .pw-rule.ok { color:var(--he-success,#10b981); }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="passwordManager()">
    <a href="javascript:history.back()" class="btn btn-sm btn-light rounded-pill px-3 mb-3 shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> {{ __('Back') }}</a>

    <div class="row justify-content-center">
        <div class="col-lg-6 col-xl-5">
            <div class="panel-card shadow-sm overflow-hidden">
                <div class="pw-hero">
                    <div class="pw-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <div style="z-index:1;">
                        <h1 class="h5 fw-bold mb-0">{{ __('Change Password') }}</h1>
                        <div class="text-white-50 small">{{ $user->name }} · {{ hostelease_phone($user->mobile) }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}" class="p-4">
                    @csrf @method('PUT')

                    @if($errors->any())
                        <div class="alert alert-danger border-0 rounded-3 py-2 small mb-3">
                            @foreach($errors->all() as $e)<div><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $e }}</div>@endforeach
                        </div>
                    @endif

                    <label class="form-label fw-bold small text-muted">{{ __('CURRENT PASSWORD') }} <span class="text-danger">*</span></label>
                    <div class="pw-field-wrap mb-3">
                        <input :type="showCurrent ? 'text' : 'password'" name="current_password" class="form-control bg-white border shadow-sm pe-5" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" @click="showCurrent = !showCurrent" tabindex="-1"><i class="fa-solid" :class="showCurrent ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                    </div>

                    <label class="form-label fw-bold small text-muted">{{ __('NEW PASSWORD') }} <span class="text-danger">*</span></label>
                    <div class="pw-field-wrap mb-2">
                        <input :type="showNew ? 'text' : 'password'" name="password" x-model="pw" class="form-control bg-white border shadow-sm pe-5" required autocomplete="new-password">
                        <button type="button" class="pw-toggle" @click="showNew = !showNew" tabindex="-1"><i class="fa-solid" :class="showNew ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                    </div>

                    <div class="pw-meter mb-2"><div class="pw-meter-bar" :style="`width:${strength.pct}%; background-color:${strength.color};`"></div></div>
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <span class="pw-rule" :class="{ ok: pw.length >= 8 }"><i class="fa-solid" :class="pw.length >= 8 ? 'fa-circle-check' : 'fa-circle'" style="font-size:.5rem;"></i>{{ __('8+ characters') }}</span>
                        <span class="pw-rule" :class="{ ok: /[A-Za-z]/.test(pw) && /\d/.test(pw) }"><i class="fa-solid" :class="/[A-Za-z]/.test(pw) && /\d/.test(pw) ? 'fa-circle-check' : 'fa-circle'" style="font-size:.5rem;"></i>{{ __('Letters & numbers') }}</span>
                        <span class="pw-rule" :class="{ ok: pw === confirm && pw.length > 0 }"><i class="fa-solid" :class="pw === confirm && pw.length > 0 ? 'fa-circle-check' : 'fa-circle'" style="font-size:.5rem;"></i>{{ __('Passwords match') }}</span>
                    </div>

                    <label class="form-label fw-bold small text-muted">{{ __('CONFIRM NEW PASSWORD') }} <span class="text-danger">*</span></label>
                    <div class="pw-field-wrap mb-4">
                        <input :type="showNew ? 'text' : 'password'" name="password_confirmation" x-model="confirm" class="form-control bg-white border shadow-sm pe-5" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm tactile-btn">
                        <i class="fa-solid fa-key me-2"></i>{{ __('Update Password') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('passwordManager', () => ({
            showCurrent: false,
            showNew: false,
            pw: '',
            confirm: '',
            get strength() {
                let score = 0;
                if (this.pw.length >= 8) score++;
                if (this.pw.length >= 12) score++;
                if (/[A-Za-z]/.test(this.pw) && /\d/.test(this.pw)) score++;
                if (/[^A-Za-z0-9]/.test(this.pw)) score++;
                const map = [
                    { pct: 8, color: '#e2e8f0' },
                    { pct: 30, color: '#ef4444' },
                    { pct: 55, color: '#f59e0b' },
                    { pct: 80, color: '#10b981' },
                    { pct: 100, color: '#10b981' },
                ];
                return this.pw.length === 0 ? { pct: 0, color: 'transparent' } : map[score];
            },
        }));
    });
</script>
@endpush
