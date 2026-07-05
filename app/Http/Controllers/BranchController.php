<?php

namespace App\Http\Controllers;

use App\Models\Hostel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the active branch (hostel) for a multi-branch hostel admin.
 */
class BranchController extends Controller
{
    public function switch(Request $request, Hostel $hostel): RedirectResponse
    {
        abort_unless($request->user()->canAccessHostel($hostel->id), 403);

        $request->session()->put('active_hostel_id', $hostel->id);

        return redirect()->route('dashboard')->with('success', "Switched to {$hostel->name}.");
    }
}
