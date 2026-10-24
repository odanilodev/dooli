<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\UpdateStorefrontSettingRequest;
use App\Services\StorefrontSettingService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class StorefrontSettingController extends ApiController
{
    public function __construct(
        private readonly StorefrontSettingService $service,
        private readonly CompanyContext $context,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize('manageStorefront');

        return ApiResponse::success($this->service->forCompany((int) $this->context->id()));
    }

    public function update(UpdateStorefrontSettingRequest $request): JsonResponse
    {
        $this->authorize('manageStorefront');

        return ApiResponse::success(
            $this->service->update((int) $this->context->id(), $request->validated()),
        );
    }
}
