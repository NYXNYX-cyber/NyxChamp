<?php

namespace App\Listeners;

use App\Events\NewCompetitionDetected;
use App\Models\User;
use App\Notifications\NewCompetitionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class LogNewCompetition
{
    /**
     * Handle the event.
     */
    public function handle(NewCompetitionDetected $event): void
    {
        Log::info("Kompetisi baru dideteksi: {$event->competition->title} [ID: {$event->competition->id}]");

        // Find users to notify based on role (admin always notified)
        // or based on level preferences (teacher/student)
        $users = User::all()->filter(function (User $user) use ($event) {
            if ($user->role === 'admin') {
                return true;
            }

            // Verify if the competition level is in the user's preferred levels
            $preferredLevels = $user->getNotificationPreference('levels', ['kabupaten', 'provinsi', 'nasional', 'internasional']);

            return in_array($event->competition->level, $preferredLevels);
        });

        if ($users->isNotEmpty()) {
            Notification::send($users, new NewCompetitionNotification($event->competition));
            Log::info("Notifikasi kompetisi baru dikirim ke " . $users->count() . " pengguna.");
        }
    }
}
