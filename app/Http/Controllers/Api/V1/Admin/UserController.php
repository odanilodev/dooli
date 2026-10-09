<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\GeneralStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\BulkUserRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\TeamService;
use App\Services\UserService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use App\Support\ImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserService $userService,
        private readonly TeamService $teamService,
        private readonly ImageStorage $images,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginator = $this->users->paginate(
            perPage: $this->perPage($request),
            filters: $request->only(['search', 'status', 'user_type_id', 'exclude_team_id', 'sort', 'direction']),
            with: ['userType'],
        );

        return ApiResponse::paginated($paginator, UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        unset($data['image_file']);

        if ($image = $request->file('image_file')) {
            $data['image'] = $this->images->store($image, 'users', (int) app(CompanyContext::class)->id());
        }

        $user = $this->users->create($data);

        return ApiResponse::success(new UserResource($user->load('userType')), 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success(new UserResource($this->users->findWithRelations($user->id)));
    }

    public function bulk(BulkUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $summary = match ($validated['action']) {
            'add-to-teams' => $this->teamService->bulkEnroll($validated['team_ids'], $validated['user_ids'], $validated['expired_at'] ?? null),
            'activate' => $this->userService->bulkSetStatus($validated['user_ids'], GeneralStatus::Active->value),
            'deactivate' => $this->userService->bulkSetStatus($validated['user_ids'], GeneralStatus::Inactive->value),
            'delete' => $this->userService->bulkDelete($validated['user_ids'], auth()->id()),
        };

        return ApiResponse::success($summary);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        unset($data['image_file']);

        if ($image = $request->file('image_file')) {
            $this->images->delete($user->image);
            $data['image'] = $this->images->store($image, 'users', (int) $user->company_id);
        }

        return ApiResponse::success(new UserResource($this->users->update($user, $data)));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return ApiResponse::noContent();
    }
}
