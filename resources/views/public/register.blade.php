<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Registration — {{ $hostel->name }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
        --he-primary: #4f46e5;
        --he-primary-hover: #4338ca;
    }
    body {
        background: #f1f5f9;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        color: #1e293b;
    }
    .hero-banner {
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        padding: 5rem 1rem 8rem;
        text-align: center;
        color: white;
        margin-bottom: -5rem;
    }
    .registration-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 1.5rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        padding: 2.5rem;
        max-width: 700px;
        margin: 0 auto 3rem;
    }
    .form-control, .form-select {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
        font-weight: 500;
    }
    .form-control:focus, .form-select:focus {
        background-color: #fff;
        border-color: var(--he-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    .btn-primary {
        background-color: var(--he-primary);
        border-color: var(--he-primary);
        border-radius: 0.75rem;
        font-weight: 700;
        padding: 0.75rem 2rem;
        transition: all 0.2s;
    }
    .btn-primary:hover {
        background-color: var(--he-primary-hover);
        border-color: var(--he-primary-hover);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);
    }
    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #475569;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    @media (max-width: 768px) {
        .registration-card {
            padding: 1.5rem;
            border-radius: 1rem;
        }
    }
  </style>
</head>
<body>
    
<div class="hero-banner">
    <h1 class="fw-bold mb-2">{{ $hostel->name }}</h1>
    <p class="mb-0 opacity-75">Student Registration Portal</p>
</div>

<div class="container position-relative z-1">
  @if(!empty($submitted))
    <div class="registration-card text-center py-5">
      <div class="mb-4 text-success" style="font-size:4rem"><i class="fa-solid fa-circle-check"></i></div>
      <h3 class="fw-bold mb-3">Application Submitted!</h3>
      <p class="text-muted mb-4 fs-5">Your details have been securely transmitted to the administration. We will review your application and confirm your registration shortly.</p>
      <a href="{{ url('register/'.$token) }}" class="btn btn-outline-primary rounded-pill px-4 fw-bold border-2">Submit another application</a>
    </div>
  @else
    <div class="registration-card">
      <div class="text-center mb-4 pb-3 border-bottom">
          <h4 class="fw-bold text-dark mb-2">Registration Form</h4>
          <p class="text-muted small mb-0">Please fill in your details below. Fields marked with <span class="text-danger">*</span> are mandatory.</p>
      </div>
      
      @if($errors->any())
        <div class="alert alert-danger rounded-3 fw-bold small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
      @endif

      <form method="POST" action="{{ url('register/'.$token) }}" enctype="multipart/form-data">
        @csrf
        
        <div class="section-title text-primary"><i class="fa-solid fa-user"></i> Personal Details</div>
        <div class="row g-3 mb-4">
            <div class="col-md-12">
                <label class="form-label fw-bold small">Full name <span class="text-danger">*</span></label>
                <input name="name" class="form-control" required maxlength="150" value="{{ old('name') }}" placeholder="Enter your full name">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small">Mobile <span class="text-danger">*</span></label>
                <input name="mobile" class="form-control" inputmode="numeric" maxlength="10" minlength="10" pattern="\d{10}" title="10-digit mobile number" required value="{{ old('mobile') }}" placeholder="10-digit mobile number">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small">Occupation <span class="text-danger">*</span></label>
                <select name="occupation_type" class="form-select" required>
                    <option value="">Select your occupation</option>
                    @foreach(config('hostelease.occupation_types') as $k => $v)
                    <option value="{{ $k }}" @selected(old('occupation_type')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="section-title text-info"><i class="fa-solid fa-users"></i> Family Contacts</div>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold small">Father's mobile <span class="text-danger">*</span></label>
                <input name="father_mobile" class="form-control" inputmode="numeric" maxlength="10" minlength="10" pattern="\d{10}" title="10-digit mobile number" required value="{{ old('father_mobile') }}" placeholder="10-digit mobile number">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small">Mother's mobile <span class="text-danger">*</span></label>
                <input name="mother_mobile" class="form-control" inputmode="numeric" maxlength="10" minlength="10" pattern="\d{10}" title="10-digit mobile number" required value="{{ old('mother_mobile') }}" placeholder="10-digit mobile number">
            </div>
        </div>

        <div class="section-title text-warning"><i class="fa-solid fa-id-card"></i> Identity & Address</div>
        <div class="row g-3 mb-4">
            <div class="col-md-12">
                <label class="form-label fw-bold small">Aadhaar number <span class="text-danger">*</span></label>
                <input name="aadhaar" class="form-control" inputmode="numeric" maxlength="12" pattern="\d{12}" required value="{{ old('aadhaar') }}" placeholder="12-digit Aadhaar number">
            </div>
            <div class="col-md-12">
                <label class="form-label fw-bold small">Full Address <span class="text-danger">*</span></label>
                <textarea name="address" class="form-control" rows="2" required maxlength="500" placeholder="Street address, locality...">{{ old('address') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small">City <span class="text-danger">*</span></label>
                <input name="city" class="form-control" required maxlength="100" value="{{ old('city') }}" placeholder="City name">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small">State <span class="text-danger">*</span></label>
                <input name="state" class="form-control" required maxlength="100" value="{{ old('state') }}" placeholder="State name">
            </div>
        </div>

        <div class="section-title text-success"><i class="fa-solid fa-calendar-check"></i> Stay Details</div>
        <div class="row g-3 mb-4">
            <div class="col-md-12">
                <label class="form-label fw-bold small">Joining Date <span class="text-danger">*</span></label>
                <input type="date" name="joining_date" class="form-control" required value="{{ old('joining_date', date('Y-m-d')) }}">
                <div class="form-text text-muted small fw-bold mt-2">
                    <i class="fa-solid fa-circle-info me-1 text-primary"></i> Joining date is also the billing date of the month for monthly guests.
                </div>
            </div>
            <div class="col-md-12 mt-4">
                <label class="form-label fw-bold small">Profile Photo <span class="text-muted fw-normal">(Optional)</span></label>
                <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
        </div>

        <button class="btn btn-primary w-100 py-3 fs-5 mt-3 shadow-sm"><i class="fa-solid fa-paper-plane me-2"></i> Submit Registration</button>
      </form>
    </div>
  @endif
  
  <div class="text-center mb-5">
      <span class="badge bg-white text-muted border px-3 py-2 rounded-pill shadow-sm fw-bold">
          <i class="fa-solid fa-building-user text-primary me-1"></i> Powered by Hostel Ease
      </span>
  </div>
</div>
</body>
</html>
