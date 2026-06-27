<?php

namespace App\Models;

use App\Services\CompetitionHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Representasi satu entri kompetisi yang sudah teragregasi dari
 * portal sumber (lihat Rancangan §2 & §3).
 *
 * Field `hash_md5` adalah pengenal dedup lintas-portal (lihat
 * AGENTS.md §3.3). Service `CompetitionHash::compute` dipakai untuk
 * menghitungnya; field di sini auto-diset di observer/create callback.
 */
class Competition extends Model
{
    /** @use HasFactory<\Database\Factories\CompetitionFactory> */
    use HasFactory;

    public const LEVEL_KABUPATEN = 'kabupaten';
    public const LEVEL_PROVINSI = 'provinsi';
    public const LEVEL_NASIONAL = 'nasional';
    public const LEVEL_INTERNASIONAL = 'internasional';

    public const LEVELS = [
        self::LEVEL_KABUPATEN,
        self::LEVEL_PROVINSI,
        self::LEVEL_NASIONAL,
        self::LEVEL_INTERNASIONAL,
    ];

    protected $fillable = [
        'title',
        'slug',
        'organizer',
        'description',
        'registration_deadline',
        'level',
        'registration_fee',
        'source_url',
        'hash_md5',
    ];

    protected function casts(): array
    {
        return [
            'registration_deadline' => 'date:Y-m-d',
            'registration_fee' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Competition $competition) {
            if (empty($competition->slug)) {
                $competition->slug = static::generateUniqueSlug($competition->title);
            }
            if (empty($competition->hash_md5)) {
                $competition->hash_md5 = CompetitionHash::compute(
                    $competition->title,
                    $competition->registration_deadline->toDateString(),
                );
            }
        });
    }

    protected static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ChatRoom::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isOpenForRegistration(): bool
    {
        return $this->registration_deadline?->isFuture() ?? false;
    }

    /**
     * Scope untuk filter halaman index kompetisi.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereDate('registration_deadline', '>=', now()->toDateString());
    }

    public function scopeOfLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }
}
