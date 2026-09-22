<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends ApiController
{
    public function __construct(private readonly AuthService $auth) {}

    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendResetLink((string) $request->validated('email'));

        // Always return the same response regardless of whether the email
        // exists, to prevent account enumeration.
        return ApiResponse::success([
            'message' => 'Se o e-mail informado estiver cadastrado, enviaremos um link de redefinição de senha.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->auth->resetPassword($request->validated());

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
