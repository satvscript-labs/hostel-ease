<?php

namespace Tests;

use App\Support\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
    }
}
