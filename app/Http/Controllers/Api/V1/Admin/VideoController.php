<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ImportVideoUrlRequest;
use App\Http\Requests\Admin\StoreVideoRequest;
use App\Http\Requests\Admin\UpdateVideoRequest;
use App\Http\Requests\Admin\VideoUploadCredentialsRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\VideoService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use App\Support\ImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends ApiController
{
    public function __construct(
        private readonly VideoService $videos,
        private readonly ImageStorage $images,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Video::class);

        $paginator = $this->videos->paginate(
            filters: $request->only(['search', 'status', 'player', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, VideoResource::class);
    }

    public function store(StoreVideoRequest $request): JsonResponse
    {
        $this->authorize('create', Video::class);

        $data = $request->validated();
        unset($data['image_file']);

        if ($image = $request->file('image_file')) {
            $data['image'] = $this->images->store($image, 'videos', (int) app(CompanyContext::class)->id());
        }

        return ApiResponse::success(new VideoResource($this->videos->create($data)), 201);
    }

    public function show(Video $video): JsonResponse
    {
        $this->authorize('view', $video);

        return ApiResponse::success(new VideoResource($video));
    }

    public function update(UpdateVideoRequest $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        $data = $request->validated();
        unset($data['image_file']);

        if ($image = $request->file('image_file')) {
            $this->images->delete($video->image);
            $data['image'] = $this->images->store($image, 'videos', (int) $video->company_id);
        }

        return ApiResponse::success(new VideoResource($this->videos->update($video, $data)));
    }

    public function destroy(Video $video): JsonResponse
    {
        $this->authorize('delete', $video);

        $this->videos->delete($video);

        return ApiResponse::noContent();
    }

    public function playback(Video $video): JsonResponse
    {
        $this->authorize('view', $video);

        return ApiResponse::success($this->videos->playback($video->id));
    }

    public function importUrl(ImportVideoUrlRequest $request): JsonResponse
    {
        $this->authorize('create', Video::class);

        return ApiResponse::success(new VideoResource($this->videos->importFromUrl($request->validated())), 201);
    }

    public function uploadCredentials(VideoUploadCredentialsRequest $request): JsonResponse
    {
        $this->authorize('create', Video::class);

        $result = $this->videos->createUploadCredentials($request->validated());

        return ApiResponse::success([
            'video' => new VideoResource($result['video']),
            'upload' => $result['upload'],
        ], 201);
    }

    public function sync(Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        return ApiResponse::success(new VideoResource($this->videos->syncStatus($video->id)));
    }

    public function syncAll(): JsonResponse
    {
        $this->authorize('viewAny', Video::class);

        return ApiResponse::success($this->videos->syncMany());
    }
}
