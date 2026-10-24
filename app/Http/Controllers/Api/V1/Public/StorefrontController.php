<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\BannerResource;
use App\Models\Company;
use App\Services\StorefrontSettingService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class StorefrontController extends ApiController
{
    public function __construct(
        private readonly StorefrontSettingService $service,
        private readonly CompanyContext $context,
    ) {}

    public function show(): JsonResponse
    {
        $companyId = (int) $this->context->id();
        $company = Company::query()->findOrFail($companyId);

        return ApiResponse::success([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'public_store_url' => $company->publicStoreUrl(),
                'gateways' => $company->gateways()
                    ->where('status', 'active')
                    ->get(['id', 'name', 'code'])
                    ->map(fn ($gateway) => [
                        'id' => $gateway->id,
                        'name' => $gateway->name,
                        'code' => $gateway->code,
                    ])
                    ->values(),
            ],
            'settings' => $this->service->forCompany($companyId),
            'banners' => BannerResource::collection($this->service->banners($companyId)),
        ]);
    }
}
