<?php

namespace Tests\Feature;

use App\Events\NewCompetitionDetected;
use App\Models\ChatRoom;
use App\Models\Competition;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\NewCompetitionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_notifications_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertStatus(200);
    }

    public function test_user_preferences_cast(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'email_enabled' => true,
                'web_enabled' => true,
                'levels' => ['nasional'],
            ]
        ]);

        $this->assertEquals(['nasional'], $user->getNotificationPreference('levels'));
        $this->assertTrue($user->getNotificationPreference('email_enabled'));
        $this->assertTrue($user->getNotificationPreference('web_enabled'));
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $payload = [
            'email_enabled' => false,
            'web_enabled' => true,
            'levels' => ['nasional', 'internasional'],
        ];

        $response = $this->actingAs($user)->patch(route('notifications.preferences.update'), $payload);

        $response->assertRedirect();
        $user->refresh();

        $this->assertEquals(false, $user->getNotificationPreference('email_enabled'));
        $this->assertEquals(true, $user->getNotificationPreference('web_enabled'));
        $this->assertEquals(['nasional', 'internasional'], $user->getNotificationPreference('levels'));
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        
        $competition = Competition::create([
            'title' => 'Lomba Test',
            'slug' => 'lomba-test',
            'organizer' => 'Penyelenggara Test',
            'description' => 'Deskripsi Test',
            'registration_deadline' => '2026-12-31',
            'level' => 'nasional',
            'registration_fee' => 0,
            'source_url' => 'https://example.com/lomba',
            'hash_md5' => md5('Lomba Test2026-12-31'),
        ]);

        $user->notify(new NewCompetitionNotification($competition));
        $notification = $user->unreadNotifications->first();
        $this->assertNotNull($notification);

        $response = $this->actingAs($user)->post(route('notifications.read', $notification->id));

        $response->assertRedirect();
        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        
        $competition = Competition::create([
            'title' => 'Lomba Test 1',
            'slug' => 'lomba-test-1',
            'organizer' => 'Penyelenggara Test',
            'description' => 'Deskripsi Test',
            'registration_deadline' => '2026-12-31',
            'level' => 'nasional',
            'registration_fee' => 0,
            'source_url' => 'https://example.com/lomba-1',
            'hash_md5' => md5('Lomba Test 12026-12-31'),
        ]);

        $user->notify(new NewCompetitionNotification($competition));
        $user->notify(new NewCompetitionNotification($competition));
        
        $this->assertEquals(2, $user->unreadNotifications()->count());

        $response = $this->actingAs($user)->post(route('notifications.read-all'));

        $response->assertRedirect();
        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_new_competition_notifies_matching_users(): void
    {
        // User 1: wants 'nasional'
        $user1 = User::factory()->create([
            'notification_preferences' => [
                'email_enabled' => true,
                'web_enabled' => true,
                'levels' => ['nasional'],
            ]
        ]);

        // User 2: wants 'kabupaten'
        $user2 = User::factory()->create([
            'notification_preferences' => [
                'email_enabled' => true,
                'web_enabled' => true,
                'levels' => ['kabupaten'],
            ]
        ]);

        // Competition level 'nasional'
        $competition = Competition::create([
            'title' => 'Lomba Nasional',
            'slug' => 'lomba-nasional',
            'organizer' => 'Penyelenggara',
            'description' => 'Deskripsi',
            'registration_deadline' => '2026-12-31',
            'level' => 'nasional',
            'registration_fee' => 0,
            'source_url' => 'https://example.com/nasional',
            'hash_md5' => md5('Lomba Nasional2026-12-31'),
        ]);

        event(new NewCompetitionDetected($competition));

        // User 1 should have 1 database notification
        $this->assertEquals(1, $user1->notifications()->count());
        $this->assertEquals('new_competition', $user1->notifications()->first()->data['type']);

        // User 2 should have 0 database notifications
        $this->assertEquals(0, $user2->notifications()->count());
    }

    public function test_teacher_invitation_notifies_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $competition = Competition::create([
            'title' => 'Lomba Test',
            'slug' => 'lomba-test',
            'organizer' => 'Penyelenggara',
            'description' => 'Deskripsi',
            'registration_deadline' => '2026-12-31',
            'level' => 'nasional',
            'registration_fee' => 0,
            'source_url' => 'https://example.com/lomba',
            'hash_md5' => md5('Lomba Test2026-12-31'),
        ]);

        $chatRoom = ChatRoom::create([
            'name' => 'Bimbingan Lomba',
            'competition_id' => $competition->id,
            'is_group' => true,
            'created_by' => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)->post(route('chat.members.invite', $chatRoom->id), [
            'email' => $student->email,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        // Student should have 1 database notification
        $this->assertEquals(1, $student->notifications()->count());
        $this->assertEquals('chat_invitation', $student->notifications()->first()->data['type']);
    }
}
