<?php

namespace App\Http\Middleware;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees a tenant is resolved. Used on public and webhook routes where
 * there is no authenticated user, so the company must come from X-Company.
 * Also validates that the company exists and is active.
 */
class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CompanyContext::class);

        if (! $context->has()) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa não informada. Envie o header X-Company.',
                'code' => 'company_required',
            ], 400);
        }

        $exists = Company::query()
            ->whereKey($context->id())
            ->where('status', GeneralStatus::Active->value)
            ->exists();

        if (! $exists) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa não encontrada ou inativa.',
                'code' => 'company_not_found',
            ], 404);
        }

        return $next($request);
    }
}
