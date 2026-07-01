<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pesan individual di dalam chat room (lihat Rancangan §3 + AGENTS.md §3.3).
 *
 * Lifecycle (Fase 8):
 * - Saat create: created_at di-set, edited_at/deleted_at NULL.
 * - Saat edit: edited_at di-update, message_text di-overwrite (TIDAK
 *   simpan history versi — pola "edit in place" sederhana ala WhatsApp).
 * - Saat delete: soft delete via deleted_at. Text asli TETAP di DB
 *   untuk audit; accessor `displayText()` mengembalikan placeholder
 *   "[Pesan dihapus]" untuk UI.
 *
 * Aturan edit (lihat Fase 8 plan):
 * - Hanya sender yang boleh edit (cek di controller, bukan di sini).
 * - Time limit 15 menit sejak created_at. Lewat itu return 403.
 *
 * Aturan delete:
 * - Sender boleh delete miliknya sendiri.
 * - Admin boleh delete pesan siapa saja (moderasi).
 * - Guru tidak boleh delete pesan orang lain (hanya admin).
 */
class ChatMessage extends Model
{
    /** @use HasFactory<\Database\Factories\ChatMessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Batas waktu edit setelah pesan dikirim (menit). */
    public const EDIT_WINDOW_MINUTES = 15;

    protected $fillable = [
        'chat_room_id',
        'sender_id',
        'message_text',
        'edited_at',
        'deleted_at',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'chat_message_id');
    }

    /**
     * Apakah pesan sudah di-soft-delete.
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Apakah pesan pernah di-edit (edited_at != null).
     */
    public function isEdited(): bool
    {
        return $this->edited_at !== null && ! $this->isDeleted();
    }

    /**
     * Text yang ditampilkan di UI. Kalau soft-deleted, return
     * placeholder (text asli tetap di property `message_text` untuk
     * audit kalau perlu di-inspect).
     */
    public function displayText(): string
    {
        if ($this->isDeleted()) {
            return '[Pesan dihapus]';
        }
        return $this->message_text;
    }

    /**
     * Apakah pesan masih dalam window edit (default 15 menit).
     */
    public function isEditable(): bool
    {
        if ($this->isDeleted()) {
            return false;
        }
        return $this->created_at?->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES)) ?? false;
    }
}
