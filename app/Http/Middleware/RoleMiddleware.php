<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate role-based access. Pakai:
 *
 *     Route::middleware(['auth', 'role:teacher'])->group(...)
 *     Route::middleware(['auth', 'role:admin,teacher'])->group(...)
 *
 * Default-nya, user yang tidak punya role yang diminta akan dapat
 * 403. Bisa di-custom via `abort()` di dalam closure.
 *
 * Lihat AGENTS.md §3.3 (users.role enum) & §3.5 (guru hanya bisa
 * bikin grup bimbingan; siswa hanya join via invite).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (empty($roles)) {
            return $next($request);
        }
        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }
        return $next($request);
    }
}
