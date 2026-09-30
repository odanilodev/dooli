<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Grants access to the administrative area to admins and to any user whose
 * user type has at least one menu permission configured. Sensitive modules
 * (menus, users, user types) remain protected by their own policies.
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user && (
            $user->isAdmin()
            || ! empty($user->userType?->permission_menu)
        );

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
