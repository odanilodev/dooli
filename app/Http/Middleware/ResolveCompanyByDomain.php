<?php

namespace App\Http\Middleware;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the request hostname.
 *
 * The frontend sends X-Company-Domain because the API may live on a different
 * origin. X-Forwarded-Host and Host are accepted as fallbacks for direct API
 * access, which keeps local tests and server-side requests simple.
 */
class ResolveCompanyByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $this->resolveDomain($request);

        if (! $domain || $this->isPlatformDomain($domain)) {
            return $this->notFound();
        }

        $company = Company::query()
            ->where('domain', $domain)
            ->where('status', GeneralStatus::Active->value)
            ->first();

        if (! $company) {
            return $this->notFound();
        }

        app(CompanyContext::class)->set((int) $company->id);
        $request->attributes->set('public_company', $company);

        return $next($request);
    }

    private function resolveDomain(Request $request): ?string
    {
        $candidates = [
            $request->header('X-Company-Domain'),
            $request->header('X-Forwarded-Host'),
            $request->getHost(),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            // X-Forwarded-Host may contain a comma-separated proxy chain.
            $candidate = trim(explode(',', $candidate)[0]);
            $domain = Company::normalizeDomain($candidate);

            if ($domain) {
                return $domain;
            }
        }

        return null;
    }

    private function isPlatformDomain(string $domain): bool
    {
        $platformDomains = array_map(
            fn (string $value) => Company::normalizeDomain($value),
            (array) config('app.platform_domains', []),
        );

        return in_array($domain, array_filter($platformDomains), true);
    }

    private function notFound(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Empresa não encontrada ou inativa.',
            'code' => 'company_not_found',
        ], 404);
    }
}
