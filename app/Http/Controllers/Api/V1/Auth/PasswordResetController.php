<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use App\Services\CompanyPasswordResetService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends ApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly CompanyPasswordResetService $companyReset,
        private readonly CompanyContext $context,
    ) {}

    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');

        if ($this->context->has()) {
            $this->companyReset->send($email, (int) $this->context->id());
        } else {
            $this->auth->sendResetLink($email);
        }

        // Always return the same response regardless of whether the email
        // exists, to prevent account enumeration.
        return ApiResponse::success([
            'message' => 'Se o e-mail informado estiver cadastrado, enviaremos um link de redefinição de senha.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->context->has()
            ? $this->companyReset->reset($request->validated(), (int) $this->context->id())
            : $this->auth->resetPassword($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(
                message: __($status),
                status: 422,
                code: 'password_reset_failed',
            );
        }

        return ApiResponse::success(['message' => __($status)]);
    }
}
