<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Fase 9 — File attachment di chat.
 * Lihat AGENTS.md §6b.
 *
 * File disimpan di disk 'chat' (lokal-disk untuk pilot, abstraction
 * siap swap ke S3/R2). Path: room-{room_id}/{yyyy}/{mm}/{ulid}-{safe_name}.
 *
 * Akses HANYA lewat controller (disk visibility=private), tidak ada
 * direct URL publik. Disk diset 'chat' di config/filesystems.php.
 */
class ChatAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_message_id',
        'uploaded_by',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/')
            && $this->mime_type !== 'image/svg+xml';
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isDocument(): bool
    {
        return in_array($this->mime_type, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], true);
    }

    /**
     * Path absolut di Storage facade (bukan absolute filesystem path).
     * Pakai ini di Blade/React: Storage::url($att->storagePath()) (kalau disk public),
     * atau stream via controller.
     */
    public function storagePath(): string
    {
        return $this->file_path;
    }

    public function fullDiskPath(): string
    {
        return Storage::disk($this->disk)->path($this->file_path);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->file_path);
    }

    /**
     * Format ukuran manusiawi: 1.4 MB, 832 KB, 12 B.
     */
    public function humanSize(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }
        return number_format($bytes / 1024 / 1024, 1).' MB';
    }
}
