<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\GeneralStatus;
use App\Enums\UserRegistrationSource;
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

        $filters = $request->only(['search', 'status', 'registration_source', 'user_type_id', 'exclude_team_id', 'sort', 'direction']);

        // Super admins are platform-level and must stay invisible to everyone else.
        $filters['exclude_super_admins'] = ! $request->user()->isSuperAdmin();

        $paginator = $this->users->paginate(
            perPage: $this->perPage($request),
            filters: $filters,
            with: ['userType'],
        );

        return ApiResponse::paginated($paginator, UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        unset($data['image_file']);
        $data['registration_source'] = UserRegistrationSource::Manual->value;

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

        if (! $request->user()->isSuperAdmin()) {
            $validated['user_ids'] = $this->withoutSuperAdmins($validated['user_ids']);
        }

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

    /**
     * Removes super admin ids from a bulk payload so non-super-admins can never
     * act on platform accounts.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function withoutSuperAdmins(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $superAdminIds = User::query()
            ->where('is_super_admin', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        return array_values(array_diff($ids, $superAdminIds));
    }
}
