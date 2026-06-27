<?php

namespace Tests\Feature;

use App\Models\ChatRoom;
use App\Models\Competition;
use App\Models\User;
use App\Services\CompetitionIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionIngestorTest extends TestCase
{
    use RefreshDatabase;

    private function ingestor(): CompetitionIngestor
    {
        return new CompetitionIngestor;
    }

    private function validItem(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Lomba Test 2026',
            'organizer' => 'Organizer Test',
            'description' => 'Deskripsi singkat',
            'registration_deadline' => '2026-12-31',
            'level' => 'nasional',
            'registration_fee' => 50000,
            'source_url' => 'https://lombahub.com/lomba/test-2026',
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Auto-create 1 admin user biar created_by chat_room tidak null.
        User::factory()->create(['role' => 'admin']);
    }

    public function test_ingest_inserts_new_competition(): void
    {
        $stats = $this->ingestor()->ingest([$this->validItem()]);

        $this->assertSame(1, $stats['inserted']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertDatabaseCount('competitions', 1);
    }

    public function test_ingest_dedup_by_hash_updates_existing(): void
    {
        $item = $this->validItem();
        $this->ingestor()->ingest([$item]);

        // Item sama persis → dedup, harus jadi UPDATE bukan INSERT baru.
        $stats = $this->ingestor()->ingest([$item]);
        $this->assertSame(0, $stats['inserted']);
        $this->assertSame(1, $stats['updated']);
        $this->assertDatabaseCount('competitions', 1);
    }

    public function test_ingest_falls_back_to_nasional_for_unknown_level(): void
    {
        $item = $this->validItem(['level' => 'duniawi']);
        $stats = $this->ingestor()->ingest([$item]);
        $this->assertSame(1, $stats['inserted']);

        $c = Competition::first();
        $this->assertSame('nasional', $c->level);
    }

    public function test_ingest_preserves_known_levels(): void
    {
        foreach (Competition::LEVELS as $level) {
            $this->ingestor()->ingest([$this->validItem([
                'title' => "Lomba $level 2026",
                'source_url' => "https://lombahub.com/lomba/$level",
            ])]);
        }
        $this->assertSame(count(Competition::LEVELS), Competition::count());
    }

    public function test_ingest_skips_item_without_title(): void
    {
        $item = $this->validItem(['title' => '']);
        $stats = $this->ingestor()->ingest([$item]);
        $this->assertSame(1, $stats['skipped']);
        $this->assertDatabaseCount('competitions', 0);
    }

    public function test_ingest_skips_item_without_deadline(): void
    {
        $item = $this->validItem(['registration_deadline' => null]);
        $stats = $this->ingestor()->ingest([$item]);
        $this->assertSame(1, $stats['skipped']);
    }

    public function test_ingest_skips_item_without_source_url(): void
    {
        $item = $this->validItem(['source_url' => '']);
        $stats = $this->ingestor()->ingest([$item]);
        $this->assertSame(1, $stats['skipped']);
    }

    public function test_ingest_normalizes_fee_negative_to_zero(): void
    {
        $item = $this->validItem(['registration_fee' => -100]);
        $this->ingestor()->ingest([$item]);
        $this->assertSame('0.00', Competition::first()->registration_fee);
    }

    public function test_ingest_creates_public_chat_room_for_new_competition(): void
    {
        $this->ingestor()->ingest([$this->validItem()]);
        $this->assertDatabaseCount('chat_rooms', 1);

        $room = ChatRoom::first();
        $this->assertTrue($room->is_group);
        $this->assertNotNull($room->competition_id);
        $this->assertStringContainsString('Diskusi', $room->name);
    }

    public function test_ingest_does_not_create_room_on_update(): void
    {
        $item = $this->validItem();
        $this->ingestor()->ingest([$item]);
        $this->ingestor()->ingest([$item]);
        // 2x ingest dengan item sama → 1 room saja (saat INSERT pertama).
        $this->assertDatabaseCount('chat_rooms', 1);
    }

    public function test_ingest_dispatches_event_for_new_competition_only(): void
    {
        $item = $this->validItem();
        // Pakai Event::fake HANYA untuk cek dispatch count; tapi karena
        // Event::fake() interfere dengan Eloquent model events, kita
        // assert via listener hitung via DB side-effect (auto-create room).
        $this->ingestor()->ingest([$item]);
        $this->assertDatabaseCount('chat_rooms', 1);

        // Insert kedua (hash sama = update) → tidak boleh bikin room baru.
        $this->ingestor()->ingest([$item]);
        $this->assertDatabaseCount('chat_rooms', 1);
    }

    public function test_ingest_handles_mixed_insert_update_skip(): void
    {
        $a = $this->validItem(['title' => 'Lomba A']);
        $b = $this->validItem(['title' => 'Lomba B']);
        $c = $this->validItem(['title' => 'Lomba C']);

        // First batch: A, B inserted
        $this->ingestor()->ingest([$a, $b]);
        // Second batch: B (update), C (insert), broken (skip)
        $stats = $this->ingestor()->ingest([
            $b,
            $c,
            ['title' => '', 'source_url' => 'x'],  // skipped
        ]);

        $this->assertSame(1, $stats['inserted']);
        $this->assertSame(1, $stats['updated']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertDatabaseCount('competitions', 3);
    }

    public function test_ingest_uses_admin_as_room_creator(): void
    {
        $this->ingestor()->ingest([$this->validItem()]);
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull(ChatRoom::first()->created_by);
        $this->assertSame($admin->id, ChatRoom::first()->created_by);
    }
}
