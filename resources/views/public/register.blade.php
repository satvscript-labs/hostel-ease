<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Student Registration — {{ $hostel->name }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  @vite(['resources/scss/app.scss'])
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    /* No per-view :root redeclaration (design law) — canonical tokens only. */
    body { background: var(--he-bg-canvas); }
    .hero-banner {
        background: linear-gradient(135deg, var(--he-primary) 0%, var(--he-accent) 100%);
        padding: 4rem 1rem 7rem;
        text-align: center;
        color: #fff;
        margin-bottom: -5rem;
        position: relative;
        overflow: hidden;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        top: -40%; right: -10%;
        width: 380px; height: 380px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.15), transparent 70%);
    }
    .hero-banner h1 { position: relative; z-index: 1; letter-spacing: -0.01em; }
    .hero-banner p { position: relative; z-index: 1; }

    .registration-card { max-width: 700px; margin: 0 auto 3rem; padding: 2.5rem; }
    .section-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--he-text-main);
        margin-bottom: 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    @media (max-width: 576px) {
        .hero-banner { padding: 2.75rem 1.1rem 5.5rem; }
        .hero-banner h1 { font-size: 1.7rem; }
        .registration-card { padding: 1.35rem; border-radius: var(--he-radius-md); }
    }
  </style>
</head>
<body>

<div class="hero-banner">
    <h1 class="fw-bold mb-2">{{ $hostel->name }}</h1>
    <p class="mb-0 opacity-75">Student Registration Portal</p>
</div>

<div class="container position-relative" style="z-index: 1;">
  @if(!empty($submitted))
    <div class="card-premium registration-card text-center py-5">
      <div class="mb-4 text-success" style="font-size: 4rem;"><i class="fa-solid fa-circle-check"></i></div>
      <h3 class="fw-bold mb-3">Application Submitted!</h3>
      <p class="text-muted mb-4 fs-5">Your details have been securely transmitted to the administration. We will review your application and confirm your registration shortly.</p>
      <a href="{{ url('register/'.$token) }}" class="btn btn-outline-primary rounded-pill px-4 fw-bold border-2">Submit another application</a>
    </div>
  @else
    <div class="card-premium registration-card">
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
        <div class="row g-3 mb-4" x-data="{ occupation: '{{ old('occupation_type') }}' }"
             @he-select-change="if ($event.detail.name === 'occupation_type') occupation = $event.detail.value">
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
                <x-he-select name="occupation_type" icon="briefcase" :submit="false"
                    :selected="old('occupation_type')" placeholder="Select your occupation"
                    :options="config('hostelease.occupation_types')" />
            </div>

            <div class="col-md-6" x-show="occupation === 'student'" x-transition style="display: none;">
                <label class="form-label fw-bold small">College / University <span class="text-danger">*</span></label>
                <input name="college" class="form-control" maxlength="255" value="{{ old('college') }}" placeholder="e.g. ABC College" :required="occupation === 'student'">
            </div>
            <div class="col-md-6" x-show="occupation === 'student'" x-transition style="display: none;">
                <label class="form-label fw-bold small">Field of Study <span class="text-danger">*</span></label>
                <input name="field_of_study" class="form-control" maxlength="255" value="{{ old('field_of_study') }}" placeholder="e.g. B.Tech Computer Science" :required="occupation === 'student'">
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

        <div class="section-title text-warning"><i class="fa-solid fa-id-card"></i> Identity &amp; Address</div>
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
                <label class="form-label fw-bold small">Aadhaar Card <span class="text-danger">*</span></label>
                <input type="file" name="aadhaar_file" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-12 mt-3">
                <label class="form-label fw-bold small">Profile Photo <span class="text-muted fw-normal">(Optional)</span></label>
                <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
        </div>

        <button class="btn btn-premium w-100 py-3 fs-5 mt-3 shadow-sm fw-bold rounded-pill"><i class="fa-solid fa-paper-plane me-2"></i> Submit Registration</button>
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
