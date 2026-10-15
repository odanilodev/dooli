<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route group to platform super admins (config de menus, tema e
 * empresas). Normal admins keep the rest of the admin area.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Você não possui permissão para realizar esta ação.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
