{{-- Shared hostel form. Expects $hostel (nullable) and $isCreate. --}}
@php($hostel = $hostel ?? null)
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Hostel Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $hostel?->name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Owner Name <span class="text-danger">*</span></label>
        <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', $hostel?->owner_name) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Mobile <span class="text-danger">*</span></label>
        <div class="input-group"><span class="input-group-text">+91</span>
            <input type="tel" name="mobile" class="form-control" maxlength="10" value="{{ old('mobile', $hostel?->mobile) }}" required></div>
        @if($isCreate)<small class="text-muted">Becomes the admin's login username.</small>@endif
    </div>
    <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $hostel?->email) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">GST Number</label>
        <input type="text" name="gst_number" class="form-control" value="{{ old('gst_number', $hostel?->gst_number) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $hostel?->address) }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">City</label>
        <input type="text" name="city" class="form-control" value="{{ old('city', $hostel?->city) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">State</label>
        <input type="text" name="state" class="form-control" value="{{ old('state', $hostel?->state) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" class="form-select" required>
            @foreach(config('hostelease.hostel_status') as $k => $label)
                <option value="{{ $k }}" @selected(old('status', $hostel?->status ?? 'active') === $k)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12"><hr><h2 class="h6 fw-bold">Subscription</h2></div>
    <div class="col-md-3">
        <label class="form-label">Start <span class="text-danger">*</span></label>
        <input type="date" name="subscription_start" class="form-control" value="{{ old('subscription_start', optional($hostel?->subscription_start)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">End <span class="text-danger">*</span></label>
        <input type="date" name="subscription_end" class="form-control" value="{{ old('subscription_end', optional($hostel?->subscription_end)->format('Y-m-d') ?? now()->addYear()->format('Y-m-d')) }}" required>
    </div>
    @if($isCreate)
        <div class="col-md-2">
            <label class="form-label">Amount (₹)</label>
            <input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount', config('hostelease.subscription_amount')) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Payment</label>
            <select name="payment_status" class="form-select">
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Method</label>
            <select name="payment_method" class="form-select">
                <option value="">—</option>
                @foreach(config('hostelease.payment_modes') as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
            </select>
        </div>
    @endif
</div>

