@extends('layouts.app')
@section('title', __('Security'))

{{-- Security (S7.1 · 34_S7_1_DESIGN.md §3) — every role: two-factor sign-in, password,
     devices, recent sign-ins. Was "Change Password" (its URL redirects here).
     BLADE ORDER: keep the multi-line PHP block first (see the owner subscription page). --}}
@php
    $tfErrors = $errors->getBag('twoFactor');
    $codes = session('recovery_codes');
    $setupMethod = $setup['method'] ?? null;
    $methodLabel = $user->two_factor_method === 'email' ? __('Email code') : __('Authenticator app');
@endphp

@push('styles')
<style>
    /* .panel-card is canonical in _premium.scss — do not redeclare. */
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

    .sc-head { display:flex; align-items:center; gap:.75rem; padding:1.1rem 1.5rem; border-bottom:1px solid rgba(15,23,42,.06); }
    .sc-head-ic { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--he-primary-soft, rgba(79,70,229,.08)); color:var(--he-primary,#4f46e5); flex-shrink:0; }
    .sc-head-ic.is-on { background:var(--he-success-soft,#d1fae5); color:#047857; }
    .sc-title { font-weight:800; color:var(--he-text-main,#0f172a); margin:0; }
    .sc-sub { font-size:.82rem; color:var(--he-text-muted,#64748b); }
    .sc-body { padding:1.25rem 1.5rem; }
    .sc-choice { display:flex; align-items:flex-start; gap:.85rem; width:100%; text-align:left; padding:1rem 1.1rem; border:1.5px solid rgba(15,23,42,.08); border-radius:1rem; background:#fff; transition:border-color .2s ease, box-shadow .2s ease; }
    .sc-choice:hover:not(:disabled) { border-color:var(--he-primary,#4f46e5); box-shadow:0 6px 18px rgba(79,70,229,.10); }
    .sc-choice:disabled { opacity:.6; cursor:not-allowed; }
    .sc-choice.on { border-color:var(--he-primary,#4f46e5); background:rgba(79,70,229,.04); }
    .sc-choice-ic { width:40px; height:40px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:var(--he-bg-surface-raised,#f1f5f9); color:var(--he-text-main,#0f172a); }
    .sc-qr { width:200px; height:200px; padding:8px; border-radius:16px; background:#fff; border:1px solid rgba(15,23,42,.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .sc-qr svg { width:100%; height:100%; }
    .sc-key { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.85rem; letter-spacing:.06em; word-break:break-all; background:var(--he-bg-surface-raised,#f8fafc); border-radius:10px; padding:.55rem .75rem; }
    .sc-code-input { font-size:1.35rem; font-weight:700; letter-spacing:.35em; text-align:center; font-variant-numeric:tabular-nums; max-width:220px; }
    .sc-codes { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:.5rem; }
    .sc-codes code { display:block; text-align:center; padding:.55rem; border-radius:10px; background:#fff; border:1px dashed rgba(15,23,42,.18); color:var(--he-text-main,#0f172a); font-size:.95rem; letter-spacing:.08em; }
    .sc-row { display:flex; align-items:center; gap:.85rem; padding:.85rem 1.5rem; }
    .sc-row + .sc-row { border-top:1px solid rgba(15,23,42,.06); }
    .sc-row-ic { width:36px; height:36px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:var(--he-bg-surface-raised,#f1f5f9); color:var(--he-text-muted,#64748b); }
    .sc-pill { font-size:.7rem; font-weight:700; border-radius:9999px; padding:.2rem .6rem; white-space:nowrap; }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="securityPage()">
    <div class="he-page-head mb-4">
        <div>
            <h1 class="he-page-title">{{ __('Security') }}</h1>
            <p class="he-page-sub">{{ __('How you sign in, and where you are signed in.') }}</p>
        </div>
    </div>

    <div class="row g-4">
        {{-- ══ Two-factor sign-in ══ --}}
        <div class="col-lg-7 d-flex flex-column gap-4">
            <div class="panel-card shadow-sm overflow-hidden" id="two-factor">
                <div class="sc-head">
                    <span class="sc-head-ic {{ $enabled ? 'is-on' : '' }}"><i class="fa-solid {{ $enabled ? 'fa-shield-halved' : 'fa-shield' }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="sc-title h6">{{ __('Two-factor sign-in') }}</h2>
                        <div class="sc-sub">{{ $enabled ? __('On — a code is asked for after your password on a new device.') : __('Off — anyone with your password can sign in.') }}</div>
                    </div>
                    @if($enabled)
                        <span class="sc-pill bg-success-subtle text-success"><i class="fa-solid fa-circle-check me-1"></i>{{ $methodLabel }}</span>
                    @endif
                </div>

                <div class="sc-body">
                    @if($tfErrors->any())
                        <div class="alert alert-danger border-0 rounded-3 py-2 small mb-3" role="alert">
                            @foreach($tfErrors->all() as $e)<div><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $e }}</div>@endforeach
                        </div>
                    @endif
                    @if(session('status'))
                        <div class="alert alert-success border-0 rounded-3 py-2 small mb-3" role="status"><i class="fa-solid fa-circle-check me-1"></i>{{ session('status') }}</div>
                    @endif

                    @if($codes)
                        {{-- Shown ONCE, right after they are made. --}}
                        <div class="rounded-4 p-3 mb-3" style="background:var(--he-warning-soft,#fef3c7);">
                            <div class="fw-bold text-dark mb-1"><i class="fa-solid fa-key me-1"></i>{{ __('Save your recovery codes') }}</div>
                            <div class="small text-muted mb-3">{{ __('If you lose your phone or cannot get the email, each of these signs you in once. Keep them somewhere safe — they will not be shown again.') }}</div>
                            <div class="sc-codes mb-3">
                                @foreach($codes as $c)<code>{{ $c }}</code>@endforeach
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold" @click="copyCodes(@js($codes))"><i class="fa-regular fa-copy me-1"></i><span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"></span></button>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold" @click="downloadCodes(@js($codes))"><i class="fa-solid fa-download me-1"></i>{{ __('Download') }}</button>
                            </div>
                        </div>
                    @endif

                    @if($enabled)
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="small text-muted">
                                <i class="fa-solid fa-key me-1"></i>{{ trans_choice(':n recovery code left|:n recovery codes left', $codesLeft, ['n' => $codesLeft]) }}
                                @if($codesLeft <= 2) · <span class="text-warning fw-semibold">{{ __('make a new set soon') }}</span> @endif
                            </span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-light border rounded-pill px-3 fw-semibold" @click="ask('codes')"><i class="fa-solid fa-rotate me-1"></i>{{ __('New recovery codes') }}</button>
                            <button type="button" class="btn btn-light text-danger border rounded-pill px-3 fw-semibold" @click="ask('disable')"><i class="fa-solid fa-power-off me-1"></i>{{ __('Turn off') }}</button>
                        </div>

                    @elseif($setupMethod === 'app')
                        <ol class="small text-muted ps-3 mb-3">
                            <li>{{ __('Install an authenticator app — Google Authenticator or Microsoft Authenticator.') }}</li>
                            <li>{{ __('In the app, add an account and scan this code.') }}</li>
                            <li>{{ __('Enter the 6-digit code the app shows.') }}</li>
                        </ol>
                        <div class="d-flex flex-wrap gap-4 align-items-start">
                            <div class="sc-qr" aria-label="{{ __('QR code for your authenticator app') }}">{!! $qr !!}</div>
                            <div class="flex-grow-1" style="min-width:220px;">
                                <div class="small text-muted mb-1">{{ __('Cannot scan? Type this key into the app:') }}</div>
                                <div class="sc-key mb-3">{{ trim(chunk_split($secret, 4, ' ')) }}</div>
                                <form method="POST" action="{{ route('security.2fa.confirm') }}">
                                    @csrf
                                    <label class="form-label fw-bold small text-muted" for="sc-code">{{ __('Code from your app') }}</label>
                                    <input id="sc-code" name="code" class="form-control sc-code-input mb-3" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">{{ __('Turn on') }}</button>
                                        <button form="sc-cancel" class="btn btn-link text-muted fw-semibold text-decoration-none">{{ __('Cancel') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    @elseif($setupMethod === 'email')
                        <p class="small text-muted">{{ __('We sent a 6-digit code to :email. Enter it to start using email codes.', ['email' => $user->email]) }}</p>
                        @if($devCode)
                            <div class="alert alert-warning py-2 small">{{ __('Local development — mail is not set up on this machine, so here is the code:') }} <strong>{{ $devCode }}</strong></div>
                        @endif
                        <form method="POST" action="{{ route('security.2fa.confirm') }}">
                            @csrf
                            <label class="form-label fw-bold small text-muted" for="sc-code">{{ __('Code from your email') }}</label>
                            <input id="sc-code" name="code" class="form-control sc-code-input mb-3" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">{{ __('Turn on') }}</button>
                                <button form="sc-resend" class="btn btn-link fw-semibold text-decoration-none" x-data="{ wait: {{ (int) $resendIn }}, init() { setInterval(() => { if (this.wait > 0) this.wait--; }, 1000); } }" :disabled="wait > 0">
                                    <span x-show="wait === 0">{{ __('Send a new code') }}</span>
                                    <span x-show="wait > 0" x-cloak>{{ __('Send a new code in') }} <span x-text="wait + 's'"></span></span>
                                </button>
                                <button form="sc-cancel" class="btn btn-link text-muted fw-semibold text-decoration-none">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                        <form id="sc-resend" method="POST" action="{{ route('security.2fa.resend') }}">@csrf</form>

                    @else
                        <p class="small text-muted mb-3">{{ __('After your password, you will also enter a 6-digit code — so a stolen password alone is not enough. Choose how you get the code:') }}</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="sc-choice" @click="ask('app')">
                                <span class="sc-choice-ic"><i class="fa-solid fa-mobile-screen"></i></span>
                                <span class="min-w-0">
                                    <span class="d-block fw-bold text-dark">{{ __('Authenticator app') }} <span class="sc-pill bg-primary-subtle text-primary ms-1">{{ __('Most secure') }}</span></span>
                                    <span class="d-block small text-muted">{{ __('Google or Microsoft Authenticator shows a new code every 30 seconds. Works without internet.') }}</span>
                                </span>
                            </button>
                            <button type="button" class="sc-choice" @click="ask('email')" @disabled(! $user->email_verified_at)>
                                <span class="sc-choice-ic"><i class="fa-solid fa-envelope"></i></span>
                                <span class="min-w-0">
                                    <span class="d-block fw-bold text-dark">{{ __('Email code') }}</span>
                                    <span class="d-block small text-muted">
                                        @if($user->email_verified_at)
                                            {{ __('We email a code to :email each time you sign in on a new device.', ['email' => $user->email]) }}
                                        @else
                                            {{ __('Needs a verified email first.') }}
                                        @endif
                                    </span>
                                </span>
                            </button>
                        </div>
                    @endif
                    <form id="sc-cancel" method="POST" action="{{ route('security.2fa.cancel') }}">@csrf</form>
                </div>
            </div>

            {{-- ══ Devices ══ --}}
            <div class="panel-card shadow-sm overflow-hidden">
                <div class="sc-head">
                    <span class="sc-head-ic"><i class="fa-solid fa-laptop"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="sc-title h6">{{ __('Devices') }}</h2>
                        <div class="sc-sub">{{ trans_choice('Signed in on :n other device.|Signed in on :n other devices.', $otherSessions, ['n' => $otherSessions]) }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold" @click="ask('others')">{{ __('Sign out everywhere else') }}</button>
                </div>
                @forelse($devices as $d)
                    <div class="sc-row">
                        <span class="sc-row-ic"><i class="fa-solid {{ str_contains((string) $d->label, 'Android') || str_contains((string) $d->label, 'iOS') ? 'fa-mobile-screen' : 'fa-desktop' }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-dark">{{ $d->label ?: __('Browser') }}
                                @if($currentDeviceHash && hash_equals($d->token_hash, $currentDeviceHash))<span class="sc-pill bg-primary-subtle text-primary ms-1">{{ __('This device') }}</span>@endif
                            </div>
                            <div class="small text-muted">{{ __('Trusted until :date', ['date' => $d->expires_at->translatedFormat('d M Y')]) }} · {{ __('last used :when', ['when' => $d->last_used_at?->diffForHumans() ?? '—']) }}</div>
                        </div>
                        <form method="POST" action="{{ route('security.devices.forget', $d->id) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger fw-semibold text-decoration-none">{{ __('Remove') }}</button>
                        </form>
                    </div>
                @empty
                    <div class="sc-row"><span class="small text-muted">{{ $enabled ? __('No trusted devices. Tick "Trust this device" when you sign in to skip the code there for 30 days.') : __('Trusted devices appear here once two-factor sign-in is on.') }}</span></div>
                @endforelse
            </div>

            {{-- ══ Recent sign-ins ══ --}}
            <div class="panel-card shadow-sm overflow-hidden">
                <div class="sc-head">
                    <span class="sc-head-ic"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <div><h2 class="sc-title h6">{{ __('Recent sign-ins') }}</h2><div class="sc-sub">{{ __('Not you? Change your password and sign out everywhere else.') }}</div></div>
                </div>
                @forelse($signIns as $s)
                    <div class="sc-row">
                        <span class="sc-row-ic"><i class="fa-solid fa-right-to-bracket"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-dark">{{ \App\Services\Auth\TwoFactor::deviceLabel((string) $s->user_agent) }}</div>
                            <div class="small text-muted text-truncate">{{ $s->created_at?->translatedFormat('d M Y, h:i A') }} · {{ $s->ip_address }}</div>
                        </div>
                        @if(str_contains((string) $s->description, 'recovery code'))
                            <span class="sc-pill bg-warning-subtle text-warning">{{ __('Recovery code') }}</span>
                        @elseif(str_contains((string) $s->description, 'two-factor'))
                            <span class="sc-pill bg-success-subtle text-success">{{ __('With code') }}</span>
                        @endif
                    </div>
                @empty
                    <div class="sc-row"><span class="small text-muted">{{ __('No sign-ins recorded yet.') }}</span></div>
                @endforelse
            </div>
        </div>

        {{-- ══ Password ══ --}}
        <div class="col-lg-5">
            <div class="panel-card shadow-sm overflow-hidden">
                <div class="pw-hero">
                    <div class="pw-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                    <div style="z-index:1;" class="min-w-0">
                        <h2 class="h5 fw-bold mb-0">{{ __('Change password') }}</h2>
                        <div class="text-white-50 small text-truncate">{{ $user->name }} · {{ hostelease_phone($user->mobile) }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}" class="p-4">
                    @csrf @method('PUT')

                    @if($errors->getBag('default')->any())
                        <div class="alert alert-danger border-0 rounded-3 py-2 small mb-3">
                            @foreach($errors->getBag('default')->all() as $e)<div><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $e }}</div>@endforeach
                        </div>
                    @endif

                    <label class="form-label fw-bold small text-muted">{{ __('Current password') }} <span class="text-danger">*</span></label>
                    <div class="pw-field-wrap mb-3">
                        <input :type="showCurrent ? 'text' : 'password'" name="current_password" class="form-control bg-white border shadow-sm pe-5" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" @click="showCurrent = !showCurrent" tabindex="-1" aria-label="{{ __('Show password') }}"><i class="fa-solid" :class="showCurrent ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                    </div>

                    <label class="form-label fw-bold small text-muted">{{ __('New password') }} <span class="text-danger">*</span></label>
                    <div class="pw-field-wrap mb-2">
                        <input :type="showNew ? 'text' : 'password'" name="password" x-model="pw" class="form-control bg-white border shadow-sm pe-5" required autocomplete="new-password">
                        <button type="button" class="pw-toggle" @click="showNew = !showNew" tabindex="-1" aria-label="{{ __('Show password') }}"><i class="fa-solid" :class="showNew ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                    </div>

                    <div class="pw-meter mb-2"><div class="pw-meter-bar" :style="`width:${strength.pct}%; background-color:${strength.color};`"></div></div>
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <span class="pw-rule" :class="{ ok: pw.length >= 8 }"><i class="fa-solid" :class="pw.length >= 8 ? 'fa-circle-check' : 'fa-circle'" style="font-size:.5rem;"></i>{{ __('8+ characters') }}</span>
                        <span class="pw-rule" :class="{ ok: /[A-Za-z]/.test(pw) && /\d/.test(pw) }"><i class="fa-solid" :class="/[A-Za-z]/.test(pw) && /\d/.test(pw) ? 'fa-circle-check' : 'fa-circle'" style="font-size:.5rem;"></i>{{ __('Letters & numbers') }}</span>
                        <span class="pw-rule" :class="{ ok: pw === confirm && pw.length > 0 }"><i class="fa-solid" :class="pw === confirm && pw.length > 0 ? 'fa-circle-check' : 'fa-circle'" style="font-size:.5rem;"></i>{{ __('Passwords match') }}</span>
                    </div>

                    <label class="form-label fw-bold small text-muted">{{ __('Confirm new password') }} <span class="text-danger">*</span></label>
                    <div class="pw-field-wrap mb-3">
                        <input :type="showNew ? 'text' : 'password'" name="password_confirmation" x-model="confirm" class="form-control bg-white border shadow-sm pe-5" required autocomplete="new-password">
                    </div>
                    <p class="small text-muted mb-4"><i class="fa-solid fa-circle-info me-1"></i>{{ __('Changing it signs you out on every other device.') }}</p>

                    <button type="submit" class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm tactile-btn">
                        <i class="fa-solid fa-key me-2"></i>{{ __('Update password') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ══ Confirm with password ══ One sheet for every action that changes how you sign in. --}}
    <template x-teleport="body">
        <div @keydown.escape.window="sheet = null" @click.self="sheet = null"
             style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center; padding:1rem;"
             :style="{ display: sheet ? 'flex' : 'none' }">
            <form method="POST" :action="actions[sheet]?.url" class="bg-white rounded-4 shadow-lg p-4 w-100" style="max-width:420px;" role="dialog" aria-modal="true">
                @csrf
                <template x-if="actions[sheet]?.method === 'DELETE'"><input type="hidden" name="_method" value="DELETE"></template>
                <input type="hidden" name="_sheet" :value="sheet">
                <h5 class="fw-bold mb-1" x-text="actions[sheet]?.title"></h5>
                <p class="small text-muted mb-3" x-text="actions[sheet]?.text"></p>
                <label class="form-label fw-bold small text-muted" for="sc-pass">{{ __('Your password') }}</label>
                <input id="sc-pass" type="password" name="password" class="form-control mb-4" required autocomplete="current-password" x-ref="pass">
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" @click="sheet = null">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn rounded-pill px-4 fw-bold" :class="actions[sheet]?.danger ? 'btn-danger' : 'btn-primary'" x-text="actions[sheet]?.cta"></button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('securityPage', () => ({
            showCurrent: false, showNew: false, pw: '', confirm: '', copied: false, sheet: null,
            actions: {
                app: { url: @js(route('security.2fa.app')), title: @js(__('Set up an authenticator app')), text: @js(__('Enter your password to continue.')), cta: @js(__('Continue')) },
                email: { url: @js(route('security.2fa.email')), title: @js(__('Use email codes')), text: @js(__('Enter your password and we will email you a code.')), cta: @js(__('Send code')) },
                codes: { url: @js(route('security.2fa.codes')), title: @js(__('Make new recovery codes')), text: @js(__('Your old recovery codes will stop working.')), cta: @js(__('Make new codes')) },
                disable: { url: @js(route('security.2fa.disable')), method: 'DELETE', danger: true, title: @js(__('Turn off two-factor sign-in?')), text: @js(__('Signing in will need only your mobile and password. Your trusted devices are forgotten.')), cta: @js(__('Turn off')) },
                others: { url: @js(route('security.logout-others')), danger: true, title: @js(__('Sign out everywhere else?')), text: @js(__('Every other device is signed out and will need your password — and code — again.')), cta: @js(__('Sign out others')) },
            },
            ask(action) { this.sheet = action; this.$nextTick(() => this.$refs.pass?.focus()); },
            copyCodes(codes) { navigator.clipboard?.writeText(codes.join('\n')).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); },
            downloadCodes(codes) {
                const blob = new Blob([@js(config('app.name').' — '.__('recovery codes')) + '\n\n' + codes.join('\n') + '\n'], { type: 'text/plain' });
                const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'hostelease-recovery-codes.txt' });
                a.click(); URL.revokeObjectURL(a.href);
            },
            get strength() {
                let score = 0;
                if (this.pw.length >= 8) score++;
                if (this.pw.length >= 12) score++;
                if (/[A-Za-z]/.test(this.pw) && /\d/.test(this.pw)) score++;
                if (/[^A-Za-z0-9]/.test(this.pw)) score++;
                const map = [{ pct: 8, color: '#e2e8f0' }, { pct: 30, color: '#ef4444' }, { pct: 55, color: '#f59e0b' }, { pct: 80, color: '#10b981' }, { pct: 100, color: '#10b981' }];
                return this.pw.length === 0 ? { pct: 0, color: 'transparent' } : map[score];
            },
            init() { @if($tfErrors->has('password')) this.sheet = @js(old('_sheet', null)); @endif },
        }));
    });
</script>
@endpush
