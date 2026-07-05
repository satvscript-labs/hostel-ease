@if(session('credentials'))
    @php($c = session('credentials'))
    <div class="alert alert-success d-flex flex-wrap align-items-center gap-3">
        <i class="fa-solid fa-key fs-4"></i>
        <div>
            <strong>Login credentials generated</strong> — share with the hostel admin (shown once):
            <div class="mt-1">
                Login (mobile): <code class="fs-6">{{ $c['mobile'] }}</code>
                &nbsp;·&nbsp; Password: <code class="fs-6">{{ $c['password'] }}</code>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-success ms-auto"
                onclick="navigator.clipboard.writeText('Login: {{ $c['mobile'] }}\nPassword: {{ $c['password'] }}');this.textContent='Copied!'">
            <i class="fa-solid fa-copy me-1"></i> Copy
        </button>
    </div>
@endif
