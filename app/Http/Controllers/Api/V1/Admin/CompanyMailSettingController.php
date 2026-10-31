<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\TestCompanyMailRequest;
use App\Http\Requests\Admin\UpdateCompanyMailSettingRequest;
use App\Http\Resources\CompanyMailSettingResource;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Services\CompanyMailSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanyMailSettingController extends ApiController
{
    public function __construct(
        private readonly CompanyMailSettingService $service,
    ) {}

    public function show(Company $company): JsonResponse
    {
        $this->authorize('manageCompanyMail');

        $setting = $this->service->find($company);

        return ApiResponse::success(
            $setting
                ? new CompanyMailSettingResource($setting)
                : $this->service->defaults($company),
        );
    }

    public function update(
        UpdateCompanyMailSettingRequest $request,
        Company $company,
    ): JsonResponse {
        $this->authorize('manageCompanyMail');

        return ApiResponse::success(
            new CompanyMailSettingResource(
                $this->service->update($company, $request->validated()),
            ),
        );
    }

    public function validateCredentials(Company $company): JsonResponse
    {
        $this->authorize('manageCompanyMail');

        $setting = $this->setting($company);
        $this->service->validateCredentials($setting);

        return ApiResponse::success(new CompanyMailSettingResource($setting->refresh()));
    }

    public function registerDomain(Company $company): JsonResponse
    {
        $this->authorize('manageCompanyMail');

        return ApiResponse::success(
            new CompanyMailSettingResource(
                $this->service->registerDomain($this->setting($company)),
            ),
        );
    }

    public function checkDomain(Company $company): JsonResponse
    {
        $this->authorize('manageCompanyMail');

        return ApiResponse::success(
            new CompanyMailSettingResource(
                $this->service->checkDomain($this->setting($company)),
            ),
        );
    }

    public function test(TestCompanyMailRequest $request, Company $company): JsonResponse
    {
        $this->authorize('manageCompanyMail');

        $sent = $this->service->sendTest(
            $this->setting($company),
            (string) $request->validated('email'),
        );

        return ApiResponse::success([
            'message' => 'E-mail de teste enviado.',
            'message_id' => $sent && method_exists($sent, 'getMessageId') ? $sent->getMessageId() : null,
        ]);
    }

    private function setting(Company $company): CompanyMailSetting
    {
        $setting = $this->service->find($company);

        if (! $setting) {
            throw new ApiException(
                'Configure o e-mail da empresa antes de continuar.',
                422,
                'company_mail_not_configured',
            );
        }

        return $setting;
    }
}
