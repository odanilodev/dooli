<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\CompanySettingService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class BrandingController extends ApiController
{
    public function __construct(
        private readonly CompanySettingService $service,
        private readonly CompanyContext $context,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->service->branding((int) $this->context->id()));
    }
}
