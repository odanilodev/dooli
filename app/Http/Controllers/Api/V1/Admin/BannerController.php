<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Repositories\BannerRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends ApiController
{
    public function __construct(private readonly BannerRepository $banners) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Banner::class);

        return ApiResponse::paginated(
            $this->banners->paginate($this->perPage($request), $request->only(['search', 'status'])),
            BannerResource::class,
        );
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $this->authorize('create', Banner::class);

        return ApiResponse::success(new BannerResource($this->banners->create($request->validated())), 201);
    }

    public function show(Banner $banner): JsonResponse
    {
        $this->authorize('view', $banner);

        return ApiResponse::success(new BannerResource($banner));
    }

    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $this->authorize('update', $banner);

        return ApiResponse::success(new BannerResource($this->banners->update($banner, $request->validated())));
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $this->authorize('delete', $banner);

        $this->banners->delete($banner);

        return ApiResponse::noContent();
    }
}
