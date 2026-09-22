<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load('userType', 'teams')),
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profiles->update($request->user(), $request->validated());

        return ApiResponse::success(new UserResource($user));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->profiles->updatePassword($request->user(), (string) $request->validated('password'));

        return ApiResponse::success(['message' => 'Senha atualizada com sucesso.']);
    }
}
