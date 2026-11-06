<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\PublicCompanyResource;
use App\Models\Company;
use App\Services\CompanySettingService;
use App\Services\StorefrontSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function __construct(
        private readonly CompanySettingService $settings,
        private readonly StorefrontSettingService $storefront,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('public_company');

        $company->branding = $this->settings->branding((int) $company->id);
        $company->storefront = [
            'settings' => $this->storefront->forCompany((int) $company->id),
            'banners' => $this->storefront->banners((int) $company->id),
        ];
        $company->setRelation(
            'gateways',
            $company->gateways()
                ->where('status', 'active')
                ->get(['id', 'name', 'code', 'credentials']),
        );

        return ApiResponse::success(new PublicCompanyResource($company));
    }
}
