<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = Notification::forUser(auth()->user())
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    public function read(Notification $notification): RedirectResponse
    {
        $this->authorizeAccess($notification);
        $notification->markAsRead();

        return back()->with('success', 'Marked as read.');
    }

    public function readAll(): RedirectResponse
    {
        Notification::forUser(auth()->user())->unread()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Notification $notification): RedirectResponse
    {
        $this->authorizeAccess($notification);
        $notification->delete();

        return back()->with('success', 'Notification removed.');
    }

    protected function authorizeAccess(Notification $notification): void
    {
        $user = auth()->user();
        $ok = $user->isSuperAdmin()
            ? is_null($notification->hostel_id)
            : $notification->hostel_id === $user->hostel_id;

        abort_unless($ok, 403);
    }
}
