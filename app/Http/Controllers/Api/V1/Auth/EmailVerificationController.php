<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Auth\ResendEmailVerificationRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EmailVerificationController extends ApiController
{
    public function __construct(private readonly AuthService $auth) {}

    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $result = $this->auth->verifyEmail($request->validated());

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function resend(ResendEmailVerificationRequest $request): JsonResponse
    {
        $this->auth->resendVerification((string) $request->validated('email'));

        // Always the same response, whether the account exists, is verified or
        // is still within the resend cooldown.
        return ApiResponse::success([
            'message' => 'Se a conta estiver pendente de confirmação, enviaremos um novo código.',
        ]);
    }
}
