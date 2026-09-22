<?php

namespace App\Http\Middleware;

use App\Enums\GeneralStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== GeneralStatus::Active->value) {
            return response()->json([
                'success' => false,
                'message' => 'Sua conta está inativa.',
                'code' => 'user_inactive',
            ], 403);
        }

        return $next($request);
    }
}
