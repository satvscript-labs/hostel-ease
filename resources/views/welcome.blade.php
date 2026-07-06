<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    
    <!-- Primary Meta Tags (SEO) -->
    <title>HostelEase — The Ultimate Hostel Management SaaS Platform</title>
    <meta name="title" content="HostelEase — The Ultimate Hostel Management SaaS Platform">
    <meta name="description" content="Manage your hostel, dorm, or PG effortlessly with HostelEase. Automate billing, track live bed occupancy, and manage students all from one stunning dashboard.">
    <meta name="keywords" content="hostel management software, pg management system, dorm management saas, bed occupancy tracking, hostel billing automation, hostelease">
    <meta name="author" content="SatvScript Solutions">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="HostelEase — The Ultimate Hostel Management SaaS Platform">
    <meta property="og:description" content="Manage your hostel, dorm, or PG effortlessly with HostelEase. Automate billing, track live bed occupancy, and manage students all from one stunning dashboard.">
    <meta property="og:image" content="{{ asset('hsms-icon.svg') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="HostelEase — The Ultimate Hostel Management SaaS Platform">
    <meta property="twitter:description" content="Manage your hostel, dorm, or PG effortlessly with HostelEase. Automate billing, track live bed occupancy, and manage students all from one stunning dashboard.">
    <meta property="twitter:image" content="{{ asset('hsms-icon.svg') }}">

    <!-- Icons & Manifest -->
    <meta name="theme-color" content="#0f172a">
    <link rel="icon" href="{{ asset('hsms-icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('hsms-icon.svg') }}">
    <link rel="manifest" href="/manifest.webmanifest">
    
    <!-- Fonts & CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss'])

    <style>
        /* Landing Page Custom Styles */
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; overflow-x: hidden; }
        
        .navbar-glass {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            width: calc(100% - 40px); max-width: 1200px; z-index: 1030;
            background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 100px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.1);
            transition: all 0.3s ease; padding: 0.75rem 1.5rem;
        }

        .hero-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 140px; /* Increased to account for floating navbar */
        }

        .hero-mesh {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            opacity: 0.6;
            background-image: 
                radial-gradient(at 80% 0%, hsla(253,16%,7%,1) 0px, transparent 50%),
                radial-gradient(at 0% 50%, hsla(253,16%,7%,1) 0px, transparent 50%),
                radial-gradient(at 80% 100%, hsla(242,100%,70%,0.3) 0px, transparent 50%),
                radial-gradient(at 0% 0%, hsla(343,100%,76%,0.2) 0px, transparent 50%);
            z-index: 1;
        }

        .hero-content { position: relative; z-index: 2; color: #fff; }
        
        .hero-title {
            font-size: clamp(3rem, 5vw, 5rem);
            font-weight: 800;
            letter-spacing: -2px;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, #ffffff, #a5b4fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-neon {
            background: linear-gradient(135deg, #4f46e5, #9333ea);
            color: white; border: none; font-weight: 600;
            padding: 1rem 2rem; border-radius: 50px;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);
            transition: all 0.3s ease;
        }
        .btn-neon:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(79, 70, 229, 0.6); color: white; }

        .btn-glass {
            background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(8px);
            color: white; border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600; padding: 1rem 2rem; border-radius: 50px;
            transition: all 0.3s ease;
        }
        .btn-glass:hover { background: rgba(255, 255, 255, 0.2); color: white; transform: translateY(-3px); }

        .feature-card {
            background: #fff; border-radius: 1.5rem; padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.03);
            transition: all 0.3s ease; height: 100%;
        }
        .feature-card:hover { transform: translateY(-10px); box-shadow: 0 25px 50px rgba(0,0,0,0.08); }
        .feature-icon {
            width: 64px; height: 64px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem; margin-bottom: 1.5rem; color: #fff;
            background: linear-gradient(135deg, #4f46e5, #9333ea);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }

        .pricing-card {
            background: #fff; border-radius: 1.5rem; padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03);
            transition: all 0.3s ease; position: relative;
        }
        .pricing-card:hover { transform: translateY(-10px); }
        .pricing-card.popular {
            border: 2px solid #4f46e5;
            box-shadow: 0 0 0 8px rgba(79, 70, 229, 0.1);
        }
        .popular-badge {
            position: absolute; top: -15px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, #4f46e5, #9333ea); color: white;
            padding: 0.5rem 1.5rem; border-radius: 50px; font-weight: bold;
            font-size: 0.85rem; letter-spacing: 1px; text-transform: uppercase;
        }

        .footer { background: #0f172a; color: #94a3b8; padding: 4rem 0 2rem; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar-glass">
        <div class="d-flex justify-content-between align-items-center w-100">
            <a href="/" class="d-flex align-items-center gap-2 text-decoration-none">
                <img src="{{ asset('hsms-icon.svg') }}" alt="Logo" style="height: 32px;">
                <span class="fs-5 fw-bold text-white tracking-tight d-none d-sm-block">{{ config('app.name', 'HostelEase') }}</span>
            </a>
            <div>
                <a href="{{ route('login') }}" class="btn btn-link text-white text-decoration-none fw-semibold me-2">Login</a>
                <a href="{{ route('register') }}" class="btn btn-neon btn-sm px-4 rounded-pill">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-mesh"></div>
        <div class="container hero-content text-center">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-4 py-2 mb-4 fw-semibold tracking-wider text-uppercase" style="backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
                        The Future of Property Management
                    </span>
                    <h1 class="hero-title">Automate Your Hostel.<br>Elevate Your Experience.</h1>
                    <p class="lead text-white opacity-75 mb-5 mx-auto" style="max-width: 700px; font-size: clamp(1rem, 2vw, 1.25rem);">
                        The ultra-premium SaaS platform designed to streamline bed assignments, automate rent collection, and provide powerful real-time insights for hostel and PG owners.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="{{ route('register') }}" class="btn btn-neon btn-lg"><i class="fa-solid fa-rocket me-2"></i> Start Your Free Trial</a>
                        <a href="#features" class="btn btn-glass btn-lg">Explore Features</a>
                    </div>
                </div>
            </div>
            
            <!-- Highly Realistic Dashboard Mockup -->
            <div class="row justify-content-center mt-5 pt-4">
                <div class="col-lg-11 col-xl-10 px-2 px-md-4">
                    <div class="position-relative mx-auto shadow-lg overflow-hidden" style="border-radius: 20px; border: 1px solid rgba(255,255,255,0.15); transform: perspective(1200px) rotateX(8deg) translateY(20px); box-shadow: 0 50px 100px -20px rgba(0,0,0,0.7); background: #f8fafc;">
                        
                        <!-- Mac OS Style Window Header -->
                        <div class="bg-dark d-flex align-items-center px-3 py-2" style="height: 32px;">
                            <div class="rounded-circle bg-danger me-2" style="width: 12px; height: 12px;"></div>
                            <div class="rounded-circle bg-warning me-2" style="width: 12px; height: 12px;"></div>
                            <div class="rounded-circle bg-success" style="width: 12px; height: 12px;"></div>
                            <div class="mx-auto text-white opacity-50 small" style="font-size: 0.7rem; font-weight: 500;">app.hostelease.com</div>
                        </div>
                        
                        <!-- Mockup Body (Sidebar + Content) -->
                        <div class="d-flex text-start" style="height: 500px; max-height: 60vh;">
                            
                            <!-- Mini Sidebar -->
                            <div class="bg-white border-end d-none d-md-flex flex-column p-3" style="width: 220px;">
                                <div class="d-flex align-items-center gap-2 mb-4">
                                    <div class="rounded border bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 0.8rem;">H</div>
                                    <span class="fw-bold fs-6 text-dark">HostelEase</span>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 fw-semibold small"><i class="fa-solid fa-chart-pie me-2"></i> Dashboard</div>
                                    <div class="p-2 text-secondary rounded-3 fw-semibold small"><i class="fa-solid fa-bed me-2"></i> Bed Matrix</div>
                                    <div class="p-2 text-secondary rounded-3 fw-semibold small"><i class="fa-solid fa-users me-2"></i> Students</div>
                                    <div class="p-2 text-secondary rounded-3 fw-semibold small"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Billing</div>
                                </div>
                            </div>
                            
                            <!-- Main Content Area -->
                            <div class="flex-grow-1 bg-light p-4 overflow-hidden d-flex flex-column">
                                <!-- Topbar Mockup -->
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="fw-bold fs-5 text-dark">Overview</div>
                                    <div class="d-flex gap-3 align-items-center">
                                        <div class="bg-white rounded-pill px-3 py-1 shadow-sm small text-muted border"><i class="fa-solid fa-magnifying-glass me-2"></i> Search...</div>
                                        <div class="rounded-circle bg-secondary bg-opacity-25" style="width: 32px; height: 32px;"></div>
                                    </div>
                                </div>
                                
                                <!-- Stats Row -->
                                <div class="row g-3 mb-4">
                                    <div class="col-4">
                                        <div class="bg-white p-3 rounded-4 shadow-sm border h-100">
                                            <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Total Revenue</div>
                                            <div class="fw-bold text-dark" style="font-size: 1.1rem;">₹4,52,000</div>
                                            <div class="text-success small fw-semibold" style="font-size: 0.7rem;"><i class="fa-solid fa-arrow-up"></i> 12%</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-3 rounded-4 shadow-sm border h-100">
                                            <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Occupancy</div>
                                            <div class="fw-bold text-dark" style="font-size: 1.1rem;">94%</div>
                                            <div class="text-primary small fw-semibold" style="font-size: 0.7rem;">141/150 Beds</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-3 rounded-4 shadow-sm border h-100">
                                            <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Pending Dues</div>
                                            <div class="fw-bold text-dark" style="font-size: 1.1rem;">₹18,500</div>
                                            <div class="text-danger small fw-semibold" style="font-size: 0.7rem;">12 Students</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Main Chart/Table Area -->
                                <div class="d-flex gap-3 flex-grow-1">
                                    <div class="bg-white rounded-4 shadow-sm border flex-grow-1 p-3 d-flex flex-column">
                                        <div class="fw-bold text-dark mb-3 small">Revenue Trend</div>
                                        <div class="flex-grow-1 d-flex align-items-end gap-2 px-2 pb-2">
                                            <div class="bg-primary bg-opacity-10 rounded-top flex-grow-1" style="height: 30%"></div>
                                            <div class="bg-primary bg-opacity-25 rounded-top flex-grow-1" style="height: 50%"></div>
                                            <div class="bg-primary bg-opacity-50 rounded-top flex-grow-1" style="height: 40%"></div>
                                            <div class="bg-primary bg-opacity-75 rounded-top flex-grow-1" style="height: 70%"></div>
                                            <div class="bg-primary rounded-top flex-grow-1" style="height: 90%; box-shadow: 0 -5px 15px rgba(79, 70, 229, 0.4);"></div>
                                        </div>
                                    </div>
                                    <div class="bg-white rounded-4 shadow-sm border w-25 p-3 d-none d-lg-block">
                                        <div class="fw-bold text-dark mb-3 small">Recent Activity</div>
                                        <div class="d-flex flex-column gap-3">
                                            <div class="d-flex gap-2 align-items-center">
                                                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:0.6rem;"><i class="fa-solid fa-check"></i></div>
                                                <div class="bg-light rounded flex-grow-1" style="height: 8px;"></div>
                                            </div>
                                            <div class="d-flex gap-2 align-items-center">
                                                <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:0.6rem;"><i class="fa-solid fa-bell"></i></div>
                                                <div class="bg-light rounded flex-grow-1" style="height: 8px;"></div>
                                            </div>
                                            <div class="d-flex gap-2 align-items-center">
                                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:0.6rem;"><i class="fa-solid fa-user"></i></div>
                                                <div class="bg-light rounded flex-grow-1" style="height: 8px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5" style="background: #f8fafc; margin-top: -100px; padding-top: 200px !important;">
        <div class="container">
            <div class="text-center mb-5 pb-3">
                <h2 class="fw-bold display-6 mb-3 text-dark">Everything You Need to Scale</h2>
                <p class="text-muted lead mx-auto" style="max-width: 600px;">Stop using spreadsheets. Discover the powerful tools designed specifically for modern hostel operators.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-bed"></i></div>
                        <h4 class="fw-bold">Visual Bed Matrix</h4>
                        <p class="text-muted mt-3">Monitor live occupancy across all your rooms and floors with our stunning, interactive capacity dashboard. Never double-book a bed again.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon bg-gradient"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                        <h4 class="fw-bold">Automated Billing</h4>
                        <p class="text-muted mt-3">Generate invoices, track pending fees, and process payments securely. Our automated ledger handles the math so you can focus on growth.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <h4 class="fw-bold">Deep Insights</h4>
                        <p class="text-muted mt-3">Make data-driven decisions with real-time revenue analytics, collection charts, and detailed reports that give you a 360° view of your business.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="py-5 bg-white">
        <div class="container py-5">
            <div class="text-center mb-5 pb-3">
                <h2 class="fw-bold display-6 mb-3 text-dark">Simple, Transparent Pricing</h2>
                <p class="text-muted lead mx-auto" style="max-width: 600px;">Start automating your hostel today. Choose a plan that grows with your business.</p>
            </div>
            
            <div class="row justify-content-center g-4">
                <!-- Monthly -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card h-100">
                        <h5 class="text-muted fw-bold mb-1">Monthly Starter</h5>
                        <p class="small text-muted mb-4">Perfect for new hostels</p>
                        <div class="mb-4">
                            <span class="display-4 fw-bold text-dark">₹499</span>
                            <span class="text-muted">/ branch / mo</span>
                        </div>
                        <ul class="list-unstyled mb-5">
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Unlimited Students</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Automated Invoicing</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Financial Reports</li>
                            <li class="mb-3 text-muted"><i class="fa-solid fa-xmark me-2"></i> Priority Support</li>
                        </ul>
                        <a href="{{ route('register') }}" class="btn btn-light btn-lg w-100 fw-bold rounded-pill">Get Started</a>
                    </div>
                </div>
                
                <!-- Yearly -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card popular h-100">
                        <div class="popular-badge">Best Value (3-for-2)</div>
                        <h5 class="text-primary fw-bold mb-1">Yearly Pro</h5>
                        <p class="small text-muted mb-4">For serious operators</p>
                        <div class="mb-4">
                            <span class="display-4 fw-bold text-dark">₹4,999</span>
                            <span class="text-muted">/ branch / yr</span>
                        </div>
                        <ul class="list-unstyled mb-5">
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Unlimited Students</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Automated Invoicing</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Financial Reports</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Every 3rd Branch Free</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-2"></i> Priority Support</li>
                        </ul>
                        <a href="{{ route('register') }}" class="btn btn-neon btn-lg w-100 rounded-pill">Start Free Trial</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <div class="d-flex justify-content-center align-items-center gap-2 mb-4">
                <img src="{{ asset('hsms-icon.svg') }}" alt="Logo" style="height: 32px; filter: grayscale(1) brightness(2);">
                <span class="fs-4 fw-bold text-white">{{ config('app.name', 'HostelEase') }}</span>
            </div>
            <div class="d-flex justify-content-center gap-4 mb-5">
                <a href="#" class="text-muted text-decoration-none">Features</a>
                <a href="#" class="text-muted text-decoration-none">Pricing</a>
                <a href="#" class="text-muted text-decoration-none">Privacy Policy</a>
                <a href="#" class="text-muted text-decoration-none">Terms of Service</a>
            </div>
            <p class="small mb-0">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p class="small mt-1">Powered by <span class="text-white fw-bold">SatvScript Solutions</span>.</p>
        </div>
    </footer>

</body>
</html>
