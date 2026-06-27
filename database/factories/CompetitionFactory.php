<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Services\CompetitionHash;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory untuk `competitions`. Pabrik ini tidak memanggil
 * observer static `creating` lewat Eloquent (lihat `Competition::booted`)
 * sehingga `hash_md5` dihitung manual di sini. Tujuannya agar
 * seeding/testing deterministic dan tidak menggantungkan callback
 * yang mungkin berubah.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Competition>
 */
class CompetitionFactory extends Factory
{
    protected $model = Competition::class;

    public function definition(): array
    {
        $title = ucfirst(implode(' ', $this->faker->words(rand(3, 5))));

        $organizers = [
            'Kemendikbud',
            'Universitas Indonesia',
            'Google Developer Student Clubs',
            'ASEAN Foundation',
            'Kementerian Pemuda dan Olahraga',
            'IEEE Indonesia Section',
            'British Council Indonesia',
            'Mozilla Indonesia',
        ];

        $deadline = $this->faker->dateTimeBetween('-1 month', '+6 month')->format('Y-m-d');

        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(6)),
            'organizer' => $this->faker->randomElement($organizers),
            'description' => $this->faker->paragraphs(rand(2, 4), true),
            'registration_deadline' => $deadline,
            'level' => $this->faker->randomElement(Competition::LEVELS),
            'registration_fee' => $this->faker->randomElement([0, 25000, 50000, 100000, 150000]),
            'source_url' => $this->faker->url(),
            'hash_md5' => CompetitionHash::compute($title, $deadline),
        ];
    }

    /**
     * Pin kompetisi ke tingkat tertentu.
     */
    public function ofLevel(string $level): static
    {
        return $this->state(fn () => ['level' => $level]);
    }

    /**
     * Pin deadline ke N hari dari sekarang.
     */
    public function deadlineInDays(int $days): static
    {
        $date = now()->addDays($days)->format('Y-m-d');
        return $this->state(fn (array $attrs) => [
            'registration_deadline' => $date,
            'hash_md5' => CompetitionHash::compute($attrs['title'], $date),
        ]);
    }

    /**
     * Pin kompetisi gratis.
     */
    public function free(): static
    {
        return $this->state(fn () => ['registration_fee' => 0]);
    }
}
