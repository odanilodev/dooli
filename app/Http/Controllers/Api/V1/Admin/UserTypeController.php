<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreUserTypeRequest;
use App\Http\Requests\Admin\UpdateUserTypeRequest;
use App\Http\Resources\UserTypeResource;
use App\Models\UserType;
use App\Repositories\UserTypeRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserTypeController extends ApiController
{
    public function __construct(private readonly UserTypeRepository $userTypes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UserType::class);

        $paginator = $this->userTypes->paginate(
            perPage: $this->perPage($request),
            filters: $request->only(['search', 'status', 'code', 'sort', 'direction']),
        );

        return ApiResponse::paginated($paginator, UserTypeResource::class);
    }

    public function store(StoreUserTypeRequest $request): JsonResponse
    {
        $this->authorize('create', UserType::class);

        $userType = $this->userTypes->create($request->validated());

        return ApiResponse::success(new UserTypeResource($userType), 201);
    }

    public function show(UserType $userType): JsonResponse
    {
        $this->authorize('view', $userType);

        return ApiResponse::success(new UserTypeResource($userType));
    }

    public function update(UpdateUserTypeRequest $request, UserType $userType): JsonResponse
    {
        $this->authorize('update', $userType);

        return ApiResponse::success(new UserTypeResource($this->userTypes->update($userType, $request->validated())));
    }

    public function destroy(UserType $userType): JsonResponse
    {
        $this->authorize('delete', $userType);

        $this->userTypes->delete($userType);

        return ApiResponse::noContent();
    }
}
