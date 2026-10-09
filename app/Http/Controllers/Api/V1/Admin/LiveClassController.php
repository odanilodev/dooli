<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreLiveClassRequest;
use App\Http\Requests\Admin\UpdateLiveClassRequest;
use App\Http\Resources\LiveClassResource;
use App\Models\LiveClass;
use App\Services\LiveClassService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveClassController extends ApiController
{
    public function __construct(private readonly LiveClassService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LiveClass::class);

        return ApiResponse::paginated(
            $this->service->paginate(
                $request->only(['search', 'status', 'sort', 'direction']),
                $this->perPage($request),
            ),
            LiveClassResource::class,
        );
    }

    public function store(StoreLiveClassRequest $request): JsonResponse
    {
        $this->authorize('create', LiveClass::class);

        $data = $request->validated();
        unset($data['image']);

        $liveClass = $this->service->create($data, $request->file('image'));

        return ApiResponse::success(new LiveClassResource($liveClass), 201);
    }

    public function show(LiveClass $liveClass): JsonResponse
    {
        $this->authorize('view', $liveClass);

        return ApiResponse::success(new LiveClassResource($this->service->find($liveClass->id)));
    }

    public function update(UpdateLiveClassRequest $request, LiveClass $liveClass): JsonResponse
    {
        $this->authorize('update', $liveClass);

        $data = $request->validated();
        unset($data['image']);

        return ApiResponse::success(
            new LiveClassResource($this->service->update($liveClass, $data, $request->file('image'))),
        );
    }

    public function destroy(LiveClass $liveClass): JsonResponse
    {
        $this->authorize('delete', $liveClass);

        $this->service->delete($liveClass);

        return ApiResponse::noContent();
    }
}
