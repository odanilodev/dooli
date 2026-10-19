<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\UpdateCompanySettingRequest;
use App\Models\Company;
use App\Services\CompanySettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanySettingController extends ApiController
{
    public function __construct(
        private readonly CompanySettingService $service,
    ) {}

    public function show(Company $company): JsonResponse
    {
        $this->authorize('manageCompanyBranding');

        return ApiResponse::success($this->service->branding($company->id));
    }

    public function update(UpdateCompanySettingRequest $request, Company $company): JsonResponse
    {
        $this->authorize('manageCompanyBranding');

        $data = $request->validated();
        unset($data['logo'], $data['logo_light'], $data['logo_dark']);

        return ApiResponse::success(
            $this->service->update(
                $company->id,
                $data,
                $request->file('logo_light'),
                $request->file('logo_dark'),
                $request->file('logo'),
            ),
        );
    }
}
