<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHostelRequest;
use App\Models\Hostel;
use App\Services\ActivityLogger;
use App\Services\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HostelController extends Controller
{
    public function __construct(
        protected HostelService $hostels,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(): View
    {
        $hostels = Hostel::withCount('students')
            ->withCount(['users as admins_count' => fn ($q) => $q->where('role', 'hostel_admin')])
            ->orderByDesc('created_at')
            ->get();

        return view('superadmin.hostels.index', compact('hostels'));
    }

    public function create(): View
    {
        return view('superadmin.hostels.create');
    }

    public function store(StoreHostelRequest $request): RedirectResponse
    {
        $result = $this->hostels->provision($request->validated());

        $this->logger->log('hostel.provision', "Provisioned hostel {$result['hostel']->name}", $result['hostel']);

        $redirect = redirect()->route('superadmin.hostels.show', $result['hostel']);

        if ($result['password'] === null) {
            // Linked to an existing owner login (same mobile = another branch).
            return $redirect->with('success',
                "Hostel created and linked to existing owner {$result['admin']->mobile} as a new branch. They use their current password.");
        }

        // Surface the generated login once so the Super Admin can share it.
        return $redirect
            ->with('credentials', [
                'mobile' => $result['admin']->mobile,
                'password' => $result['password'],
            ])
            ->with('success', 'Hostel created and admin login generated.');
    }

    public function show(Hostel $hostel): View
    {
        $hostel->loadCount('students', 'rooms', 'beds')
            ->load(['admins', 'subscriptions' => fn ($q) => $q->latest('end_date')]);

        return view('superadmin.hostels.show', compact('hostel'));
    }

    public function edit(Hostel $hostel): View
    {
        return view('superadmin.hostels.edit', compact('hostel'));
    }

    public function update(StoreHostelRequest $request, Hostel $hostel): RedirectResponse
    {
        $hostel->update($request->safe()->only([
            'name', 'owner_name', 'mobile', 'email', 'address', 'city', 'state',
            'gst_number', 'subscription_start', 'subscription_end', 'status',
        ]));

        $this->logger->log('hostel.update', "Updated hostel {$hostel->name}", $hostel);

        return redirect()->route('superadmin.hostels.show', $hostel)->with('success', 'Hostel updated.');
    }

    public function destroy(Hostel $hostel): RedirectResponse
    {
        $this->logger->log('hostel.delete', "Deleted hostel {$hostel->name}", $hostel);
        $hostel->delete();

        return redirect()->route('superadmin.hostels.index')->with('success', 'Hostel deleted.');
    }
}
