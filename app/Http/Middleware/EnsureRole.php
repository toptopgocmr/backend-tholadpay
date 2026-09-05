<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * AJOUT (2026-09-05, demande explicite) : middleware générique pour restreindre
 * une route à un ou plusieurs rôles Laratrust précis.
 *
 * Usage dans routes/api.php (à placer APRES 'jwt.auth' dans le groupe de
 * middleware pour que Auth::user() soit déjà résolu) :
 *   ['middleware' => ['jwt.auth', 'role:administrator']]
 *   ['middleware' => ['jwt.auth', 'role:administrator,finance_manager']]
 */
class EnsureRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        // ...$roles peut arriver sous la forme ['administrator,finance_manager']
        // (un seul segment avec virgules) selon la syntaxe utilisée en route.
        $roles = collect($roles)
            ->flatMap(function ($r) {
                return explode(',', $r);
            })
            ->map('trim')
            ->filter()
            ->all();

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'Action réservée au(x) profil(s) : ' . implode(', ', $roles)
        ], 403);
    }
}
