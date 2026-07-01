<?php

namespace App\Notifications;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ChatRoom $chatRoom,
        public User $inviter
    ) {
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

        // Check if email notifications are enabled
        if ($notifiable->getNotificationPreference('email_enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $competitionName = $this->chatRoom->competition ? $this->chatRoom->competition->title : 'Umum';

        return (new MailMessage)
            ->subject('Undangan Bimbingan Baru: ' . $this->chatRoom->name)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Anda telah diundang oleh ' . $this->inviter->name . ' untuk bergabung ke grup bimbingan.')
            ->line('**Nama Grup:** ' . $this->chatRoom->name)
            ->line('**Terkait Kompetisi:** ' . $competitionName)
            ->action('Masuk ke Chat Bimbingan', url('/chat/' . $this->chatRoom->id))
            ->line('Selamat belajar dan berkolaborasi!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chat_invitation',
            'chat_room_id' => $this->chatRoom->id,
            'chat_room_name' => $this->chatRoom->name,
            'inviter_id' => $this->inviter->id,
            'inviter_name' => $this->inviter->name,
            'competition_title' => $this->chatRoom->competition?->title,
            'message' => $this->inviter->name . ' mengundang Anda ke grup bimbingan "' . $this->chatRoom->name . '".',
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
