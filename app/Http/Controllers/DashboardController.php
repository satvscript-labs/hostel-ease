<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Routes the authenticated user to the dashboard for their role.
 */
class DashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        return Auth::user()->isSuperAdmin()
            ? redirect()->route('superadmin.dashboard')
            : redirect()->route('admin.dashboard');
    }
}
