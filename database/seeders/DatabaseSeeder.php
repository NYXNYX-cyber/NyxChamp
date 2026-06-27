<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed data awal: 1 admin + 1 guru + 1 siswa (untuk eksplorasi
     * alur role & RBAC manual). Tambah user random via factory saat
     * perlu volume data.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Admin NyxChamp',
                'email' => 'admin@nyxchamp.test',
                'role' => User::ROLE_ADMIN,
                'institution' => 'Tim NyxChamp',
            ],
            [
                'name' => 'Guru Pembimbing',
                'email' => 'guru@nyxchamp.test',
                'role' => User::ROLE_TEACHER,
                'institution' => 'SMA Negeri 1 Contoh',
            ],
            [
                'name' => 'Siswa Peserta',
                'email' => 'siswa@nyxchamp.test',
                'role' => User::ROLE_STUDENT,
                'institution' => 'SMA Negeri 1 Contoh',
            ],
        ];

        foreach ($accounts as $a) {
            User::updateOrCreate(
                ['email' => $a['email']],
                array_merge($a, [
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]),
            );
        }
    }
}
