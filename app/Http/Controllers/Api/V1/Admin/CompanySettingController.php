<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\UpdateCompanySettingRequest;
use App\Services\CompanySettingService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class CompanySettingController extends ApiController
{
    public function __construct(
        private readonly CompanySettingService $service,
        private readonly CompanyContext $context,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->service->branding($this->companyId()));
    }

    public function update(UpdateCompanySettingRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['logo']);

        return ApiResponse::success(
            $this->service->update($this->companyId(), $data, $request->file('logo')),
        );
    }

    private function companyId(): int
    {
        return (int) ($this->context->id() ?? request()->user()->company_id);
    }
}
