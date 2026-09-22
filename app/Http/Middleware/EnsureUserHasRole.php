<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin') or ->middleware('role:admin,instructor')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $code = $user?->userType?->code;

        if (! $user || ! $code || ! in_array($code, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não possui permissão para realizar esta ação.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
