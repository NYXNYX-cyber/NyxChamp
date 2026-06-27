<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `role` & `institution` ke tabel users.
 *
 * Kolom ini didefinisikan di AGENTS.md §3.3 (keputusan terkunci) dan
 * di Rancangan §3 (tabel users). Enum role: student | teacher | admin.
 * Default 'student' sesuai rancangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['student', 'teacher', 'admin'])
                ->default('student')
                ->after('password');
            $table->string('institution')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'institution']);
        });
    }
};
