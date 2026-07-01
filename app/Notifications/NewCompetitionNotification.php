<?php

namespace App\Notifications;

use App\Models\Competition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewCompetitionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Competition $competition)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        // Check if web notifications are enabled
        if ($notifiable->getNotificationPreference('web_enabled', true)) {
            $channels[] = 'database';
            $channels[] = 'broadcast';
        }

        // Check if email notifications are enabled and matches level preferences
        $emailEnabled = $notifiable->getNotificationPreference('email_enabled', true);
        $preferredLevels = $notifiable->getNotificationPreference('levels', ['kabupaten', 'provinsi', 'nasional', 'internasional']);

        if ($emailEnabled && in_array($this->competition->level, $preferredLevels)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lomba Baru Tersedia: ' . $this->competition->title)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Ada kompetisi baru yang sesuai dengan preferensi tingkat Anda.')
            ->line('**Judul:** ' . $this->competition->title)
            ->line('**Penyelenggara:** ' . $this->competition->organizer)
            ->line('**Tingkat:** ' . ucfirst($this->competition->level))
            ->line('**Tenggat Pendaftaran:** ' . $this->competition->registration_deadline)
            ->action('Jelajahi Lomba', url('/lomba/' . $this->competition->slug))
            ->line('Terima kasih telah menggunakan ' . config('app.name') . '!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_competition',
            'competition_id' => $this->competition->id,
            'title' => $this->competition->title,
            'slug' => $this->competition->slug,
            'organizer' => $this->competition->organizer,
            'level' => $this->competition->level,
            'message' => 'Kompetisi baru "' . $this->competition->title . '" telah ditambahkan!',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'read_at' => null,
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
