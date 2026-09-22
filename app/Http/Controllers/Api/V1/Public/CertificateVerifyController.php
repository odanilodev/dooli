<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\CertificateVerifyResource;
use App\Services\CertificateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CertificateVerifyController extends ApiController
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function verify(string $code): JsonResponse
    {
        $certificate = $this->certificates->verify($code);

        return ApiResponse::success(new CertificateVerifyResource($certificate));
    }
}
