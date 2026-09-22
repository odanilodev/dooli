<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant (company).
 *
 * - Authenticated requests: company is the user's own company (cannot be spoofed).
 * - Public/webhook requests: company comes from the "X-Company" header (company id).
 */
class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CompanyContext::class);

        $user = $request->user();

        if ($user && $user->company_id) {
            $context->set((int) $user->company_id);
        } elseif ($request->filled('company_id')) {
            $context->set((int) $request->input('company_id'));
        } elseif ($request->header('X-Company')) {
            $context->set((int) $request->header('X-Company'));
        }

        return $next($request);
    }
}
