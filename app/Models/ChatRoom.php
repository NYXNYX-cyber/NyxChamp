<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ruang obrolan (lihat Rancangan §3 + AGENTS.md §3.3).
 *
 * Aturan penting:
 * - `competition_id` nullable: grup non-kompetisi (mis. diskusi internal
 *   guru) diperbolehkan.
 * - `is_group=true` untuk grup bimbingan; `false` untuk 1-on-1 (cadangan,
 *   saat ini semua grup adalah is_group=true).
 * - Channel Reverb untuk room ini didefinisikan di `routes/channels.php`
 *   (lihat Fase 8).
 */
class ChatRoom extends Model
{
    /** @use HasFactory<\Database\Factories\ChatRoomFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'competition_id',
        'name',
        'is_group',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_members')
            ->withPivot('joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    /**
     * Channel Reverb yang dipakai untuk room ini. Lihat Fase 8 untuk
     * authorization logic.
     */
    public function broadcastChannel(): string
    {
        return 'chat.room.' . $this->id;
    }
}
