<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreVideoPlatformRequest;
use App\Http\Requests\Admin\UpdateVideoPlatformRequest;
use App\Http\Resources\VideoPlatformResource;
use App\Models\VideoPlatform;
use App\Services\VideoPlatformService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoPlatformController extends ApiController
{
    public function __construct(private readonly VideoPlatformService $platforms) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VideoPlatform::class);

        $paginator = $this->platforms->paginate(
            filters: $request->only(['search', 'status', 'code', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, VideoPlatformResource::class);
    }

    public function store(StoreVideoPlatformRequest $request): JsonResponse
    {
        $this->authorize('create', VideoPlatform::class);

        $platform = $this->platforms->create($request->validated());

        return ApiResponse::success(new VideoPlatformResource($platform), 201);
    }

    public function show(VideoPlatform $videoPlatform): JsonResponse
    {
        $this->authorize('view', $videoPlatform);

        return ApiResponse::success(new VideoPlatformResource($videoPlatform));
    }

    public function update(UpdateVideoPlatformRequest $request, VideoPlatform $videoPlatform): JsonResponse
    {
        $this->authorize('update', $videoPlatform);

        return ApiResponse::success(
            new VideoPlatformResource($this->platforms->update($videoPlatform, $request->validated())),
        );
    }

    public function destroy(VideoPlatform $videoPlatform): JsonResponse
    {
        $this->authorize('delete', $videoPlatform);

        $this->platforms->delete($videoPlatform);

        return ApiResponse::noContent();
    }
}
