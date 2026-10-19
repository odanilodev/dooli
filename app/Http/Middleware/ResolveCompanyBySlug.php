<?php

namespace App\Http\Middleware;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyBySlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('companySlug');

        $company = Company::query()
            ->where('slug', $slug)
            ->where('status', GeneralStatus::Active->value)
            ->firstOrFail();

        app(CompanyContext::class)->set((int) $company->id);
        $request->attributes->set('public_company', $company);

        return $next($request);
    }
}
