@extends('layouts.app')
@section('title', 'Change Password')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Change Password</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row">
    <div class="col-lg-6">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <i class="fa-solid fa-circle-user fs-2 text-primary"></i>
                    <div>
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="text-muted small">{{ hsms_phone($user->mobile) }} · {{ config('hsms.roles.'.$user->role, ucfirst($user->role)) }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="text" name="current_password" class="form-control pw-field" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="text" name="password" class="form-control pw-field" required autocomplete="off">
                        <small class="text-muted">At least 8 characters.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="text" name="password_confirmation" class="form-control pw-field" required autocomplete="off">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="show-pw" checked
                               onchange="document.querySelectorAll('.pw-field').forEach(i => i.type = this.checked ? 'text' : 'password')">
                        <label class="form-check-label" for="show-pw">Show passwords</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key me-1"></i> Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
