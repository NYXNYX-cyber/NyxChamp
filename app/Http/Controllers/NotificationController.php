<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Notifications/Index', [
            'notifications' => $user->notifications()->paginate(15),
            'preferences' => [
                'email_enabled' => $user->getNotificationPreference('email_enabled', true),
                'web_enabled' => $user->getNotificationPreference('web_enabled', true),
                'levels' => $user->getNotificationPreference('levels', ['kabupaten', 'provinsi', 'nasional', 'internasional']),
            ],
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back()->with('status', 'notifikasi-dibaca');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'semua-notifikasi-dibaca');
    }

    /**
     * Update the notification preferences.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_enabled' => ['required', 'boolean'],
            'web_enabled' => ['required', 'boolean'],
            'levels' => ['required', 'array'],
            'levels.*' => ['string', 'in:kabupaten,provinsi,nasional,internasional'],
        ]);

        $user = $request->user();
        $user->notification_preferences = $validated;
        $user->save();

        return back()->with('status', 'preferensi-diperbarui');
    }
}
