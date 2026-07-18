<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global instant-search used by the topbar. Tenant scoping keeps a hostel
 * admin's results within their hostel; the super admin searches hostels.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        return response()->json([
            'results' => $request->user()->isSuperAdmin()
                ? $this->hostels($q)
                : $this->tenantResults($q),
        ]);
    }

    protected function tenantResults(string $q): array
    {
        $digits = preg_replace('/\D+/', '', $q);
        $results = [];

        Student::query()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->when($digits !== '', fn ($x) => $x->orWhere('mobile', 'like', "%{$digits}%")))
            ->limit(6)->get()
            ->each(function ($s) use (&$results) {
                $results[] = [
                    'group' => 'Students', 'icon' => 'fa-user',
                    'label' => $s->name, 'sub' => hostelease_phone($s->mobile),
                    'url' => route('admin.students.show', $s),
                ];
            });

        Room::with('floor')->where('room_number', 'like', "%{$q}%")->limit(5)->get()
            ->each(function ($r) use (&$results) {
                $results[] = [
                    'group' => 'Rooms', 'icon' => 'fa-door-open',
                    'label' => "Room {$r->room_number}", 'sub' => $r->floor->name,
                    'url' => route('admin.rooms.edit', $r),
                ];
            });

        Bed::with('room')->where('bed_number', 'like', "%{$q}%")->limit(5)->get()
            ->each(function ($b) use (&$results) {
                $results[] = [
                    'group' => 'Beds', 'icon' => 'fa-bed',
                    'label' => "Bed {$b->bed_number}", 'sub' => "Room {$b->room->room_number} · ".ucfirst($b->status),
                    'url' => route('admin.beds.history', $b),
                ];
            });

        // Staff are a directory too (W12) — the search knew students but not
        // the team.
        \App\Models\Staff::query()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('designation', 'like', "%{$q}%")
                ->when($digits !== '', fn ($x) => $x->orWhere('mobile', 'like', "%{$digits}%")))
            ->limit(5)->get()
            ->each(function ($s) use (&$results) {
                $results[] = [
                    'group' => 'Staff', 'icon' => 'fa-user-tie',
                    'label' => $s->name, 'sub' => ($s->designation ?: 'Staff').' · '.hostelease_phone($s->mobile),
                    'url' => route('admin.staff.show', $s),
                ];
            });

        // A receipt number in hand → the student it belongs to (W12). The
        // commonest "find this" moment at a front desk.
        \App\Models\Payment::with('student')
            ->where('receipt_number', 'like', "%{$q}%")
            ->whereHas('student')
            ->limit(4)->get()
            ->each(function ($p) use (&$results) {
                $results[] = [
                    'group' => 'Receipts', 'icon' => 'fa-receipt',
                    'label' => $p->receipt_number,
                    'sub' => $p->student->name.' · '.hostelease_money($p->amount),
                    'url' => route('admin.students.show', $p->student),
                ];
            });

        return $results;
    }

    protected function hostels(string $q): array
    {
        $results = Hostel::where('name', 'like', "%{$q}%")
            ->orWhere('owner_name', 'like', "%{$q}%")
            ->orWhere('mobile', 'like', "%{$q}%")
            ->limit(8)->get()
            ->map(fn ($h) => [
                'group' => 'Hostels', 'icon' => 'fa-hotel',
                'label' => $h->name, 'sub' => $h->owner_name.' · '.hostelease_phone($h->mobile),
                'url' => url('superadmin/hostels/'.$h->id),
            ])->all();

        // Customers (W12) — the account-level view is where billing actions
        // live, so the owner's NAME should land there, not only on a branch.
        \App\Models\SubscriptionAccount::with('owner')
            ->whereHas('owner', fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('mobile', 'like', "%{$q}%"))
            ->limit(6)->get()
            ->each(function ($a) use (&$results) {
                $results[] = [
                    'group' => 'Customers', 'icon' => 'fa-user-gear',
                    'label' => $a->owner?->name ?? 'Account #'.$a->id,
                    'sub' => ($a->owner ? hostelease_phone($a->owner->mobile).' · ' : '').ucfirst((string) ($a->status->value ?? $a->status)),
                    'url' => route('superadmin.accounts.show', $a),
                ];
            });

        return $results;
    }
}

