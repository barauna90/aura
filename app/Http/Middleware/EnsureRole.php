<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** RBAC hierárquico: STUDENT < REVIEWER < ADMIN < SUPER_ADMIN. Uso: middleware('role:ADMIN'). */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $minimum): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole($minimum)) {
            abort(403, 'Permissão insuficiente.');
        }

        return $next($request);
    }
}
