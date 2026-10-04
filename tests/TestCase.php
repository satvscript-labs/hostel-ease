<?php

namespace Tests;

use App\Support\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The tenant holder is a process-static (H6). php-fpm gives every request
        // a fresh process, but the whole test suite runs in ONE process, so a
        // tenant set by an earlier test would leak into the next (and its fresh
        // DB has no such hostel → FK errors on audit logs). Reset per test so
        // isolation holds regardless of run order.
        Tenant::set(null);

        // No test may make a REAL outbound HTTP call (S3 audit). The billing code now
        // talks to Razorpay as a side effect of ordinary actions — cancelling links a
        // payment has made pointless, checking an abandoned checkout before replacing
        // it — so an unfaked path would quietly try the live API with whatever keys
        // the test configured. Every request must be faked explicitly; a stray one
        // fails the test instead of leaving the building.
        Http::preventStrayRequests();
    }
}
