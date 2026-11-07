<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreStoreProductRequest;
use App\Http\Requests\Admin\UpdateStoreProductRequest;
use App\Http\Resources\StoreProductResource;
use App\Models\StoreProduct;
use App\Services\DripReleaseService;
use App\Services\StoreProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreProductController extends ApiController
{
    public function __construct(
        private readonly StoreProductService $storeProducts,
        private readonly DripReleaseService $drip,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StoreProduct::class);

        $paginator = $this->storeProducts->paginate(
            filters: $request->only(['search', 'status', 'course_id', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, StoreProductResource::class);
    }

    public function store(StoreStoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', StoreProduct::class);

        $data = $request->validated();
        unset($data['image_file'], $data['module_drips']);

        $storeProduct = $this->storeProducts->create($data, $request->file('image_file'));

        if ($request->has('team_ids')) {
            $this->storeProducts->syncTeams($storeProduct, $request->validated('team_ids') ?? []);
        }

        if ($request->has('module_drips')) {
            $this->drip->syncIntervals($storeProduct, $request->validated('module_drips') ?? []);
        }

        return ApiResponse::success(new StoreProductResource($storeProduct->load(['course', 'teams.courses'])), 201);
    }

    public function show(StoreProduct $storeProduct): JsonResponse
    {
        $this->authorize('view', $storeProduct);

        return ApiResponse::success(new StoreProductResource($this->storeProducts->find($storeProduct->id)));
    }

    public function update(UpdateStoreProductRequest $request, StoreProduct $storeProduct): JsonResponse
    {
        $this->authorize('update', $storeProduct);

        $data = $request->validated();
        unset($data['image_file'], $data['module_drips']);

        $updated =$this->storeProducts->update(
            $storeProduct,
            $data,
            $request->file('image_file'),
            $request->exists('image'),
        );

        if ($request->has('team_ids')) {
            $this->storeProducts->syncTeams($updated, $request->validated('team_ids') ?? []);
        }

        if ($request->has('module_drips')) {
            $this->drip->syncIntervals($updated, $request->validated('module_drips') ?? []);
        }

        return ApiResponse::success(new StoreProductResource($updated->load(['course', 'teams.courses'])));
    }

    public function destroy(StoreProduct $storeProduct): JsonResponse
    {
        $this->authorize('delete', $storeProduct);

        $this->storeProducts->delete($storeProduct);

        return ApiResponse::noContent();
    }
}
