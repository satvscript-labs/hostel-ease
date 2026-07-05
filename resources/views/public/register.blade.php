<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Registration — {{ $hostel->name }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f3f4f6} .card{border:none;border-radius:16px}
    .brand{color:#2563eb;font-weight:700}
  </style>
</head>
<body>
<div class="container py-4" style="max-width:560px;">
  <div class="text-center mb-3">
    <div class="brand h3 mb-0">{{ $hostel->name }}</div>
    <div class="text-muted">Student Registration</div>
  </div>

  @if(!empty($submitted))
    <div class="card shadow-sm"><div class="card-body text-center py-5">
      <div style="font-size:3rem">✅</div>
      <h4 class="mt-2">Thank you!</h4>
      <p class="text-muted">Your details have been submitted. The hostel admin will review and confirm your registration shortly.</p>
      <a href="{{ url('register/'.$token) }}" class="btn btn-outline-primary">Submit another</a>
    </div></div>
  @else
    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <div class="card shadow-sm"><div class="card-body">
      <p class="text-muted small">Fill in your details to register at this hostel. Fields marked * are required.</p>
      <form method="POST" action="{{ url('register/'.$token) }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3"><label class="form-label">Full name *</label>
          <input name="name" class="form-control" required value="{{ old('name') }}"></div>
        <div class="row">
          <div class="col-6 mb-3"><label class="form-label">Mobile *</label>
            <input name="mobile" class="form-control" inputmode="numeric" maxlength="10" required value="{{ old('mobile') }}"></div>
          <div class="col-6 mb-3"><label class="form-label">Occupation *</label>
            <select name="occupation_type" class="form-select" required>
              @foreach(config('hsms.occupation_types') as $k => $v)
                <option value="{{ $k }}" @selected(old('occupation_type')===$k)>{{ $v }}</option>
              @endforeach
            </select></div>
        </div>
        <div class="row">
          <div class="col-6 mb-3"><label class="form-label">Father's mobile</label>
            <input name="father_mobile" class="form-control" inputmode="numeric" maxlength="10" value="{{ old('father_mobile') }}"></div>
          <div class="col-6 mb-3"><label class="form-label">Mother's mobile</label>
            <input name="mother_mobile" class="form-control" inputmode="numeric" maxlength="10" value="{{ old('mother_mobile') }}"></div>
        </div>
        <div class="mb-3"><label class="form-label">Aadhaar number</label>
          <input name="aadhaar" class="form-control" inputmode="numeric" maxlength="12" value="{{ old('aadhaar') }}"></div>
        <div class="mb-3"><label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea></div>
        <div class="row">
          <div class="col-6 mb-3"><label class="form-label">City</label>
            <input name="city" class="form-control" value="{{ old('city') }}"></div>
          <div class="col-6 mb-3"><label class="form-label">State</label>
            <input name="state" class="form-control" value="{{ old('state') }}"></div>
        </div>
        <div class="mb-3"><label class="form-label">Photo (optional)</label>
          <input type="file" name="photo" class="form-control" accept="image/*"></div>
        <button class="btn btn-primary w-100 py-2">Submit Registration</button>
      </form>
    </div></div>
  @endif
  <p class="text-center text-muted small mt-3">Powered by HSMS</p>
</div>
</body>
</html>
