<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Repositories\BannerRepository;
use App\Services\BannerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends ApiController
{
    public function __construct(
        private readonly BannerRepository $banners,
        private readonly BannerService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Banner::class);

        return ApiResponse::paginated(
            $this->banners->paginate($this->perPage($request), $request->only(['search', 'status', 'placement'])),
            BannerResource::class,
        );
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $this->authorize('create', Banner::class);

        $data = $request->validated();
        unset($data['image']);

        $banner = $this->service->create($data, $request->file('image'));

        return ApiResponse::success(new BannerResource($banner), 201);
    }

    public function show(Banner $banner): JsonResponse
    {
        $this->authorize('view', $banner);

        return ApiResponse::success(new BannerResource($banner));
    }

    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $this->authorize('update', $banner);

        $data = $request->validated();
        unset($data['image']);

        return ApiResponse::success(new BannerResource($this->service->update($banner, $data, $request->file('image'))));
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $this->authorize('delete', $banner);

        $this->service->delete($banner);

        return ApiResponse::noContent();
    }
}
