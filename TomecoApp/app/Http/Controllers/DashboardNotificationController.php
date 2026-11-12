<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardNotificationController extends Controller
{
    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function notificationFeed(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->take(8)->get();

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'is_unread' => $notification->read_at === null,
                'created_at' => $notification->created_at->diffForHumans(),
                'read_url' => route('dashboard.notifications.read-one', $notification->id),
            ])->values(),
        ]);
    }

    public function markNotificationRead(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $destination = $notification->data['url'] ?? route('dashboard', absolute: false);

        // Older notifications may contain the host that created them. Keep only
        // their local path so LAN clients stay on the host they are using.
        if (is_string($destination) && filter_var($destination, FILTER_VALIDATE_URL)) {
            $parts = parse_url($destination);
            $destination = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return redirect()->to($destination);
    }

    public function deleteNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications' => ['required', 'array', 'min:1'],
            'notifications.*' => ['required', 'string'],
        ]);

        $deleted = $request->user()->notifications()
            ->whereIn('id', $validated['notifications'])
            ->delete();

        return back()->with('success', $deleted.' selected notification(s) deleted.');
    }

    public function deleteAllNotifications(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back()->with('success', 'All notifications deleted.');
    }
}
