<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\GeneralStatus;
use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\CertificatePublicResource;
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

        return ApiResponse::success(new CertificateVerifyResource($certificate))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Full presentation payload for the public share page. Unlike verify, this
     * route does not require a tenant header: the UUID resolves the company.
     */
    public function show(string $code): JsonResponse
    {
        $certificate = $this->certificates->verify($code);

        if ($certificate->company?->status !== GeneralStatus::Active->value) {
            throw new ResourceNotFoundException('Certificado não encontrado ou inválido.');
        }

        return ApiResponse::success(new CertificatePublicResource($certificate))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
