<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('menu.access:/admin/cursos') (several links allowed, any match passes).
 *
 * Admins and super admins always pass. Any other user type needs the menu
 * (matched by its link) listed in its permission_menu.
 */
class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next, string ...$links): Response
    {
        $user = $request->user();

        $allowed = $user && collect($links)->contains(fn (string $link) => $user->canAccessMenu($link));

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Você não possui permissão para realizar esta ação.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
