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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends ApiController
{
    public function __construct(private readonly VideoService $videos) {}

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

        return ApiResponse::success(new VideoResource($this->videos->create($request->validated())), 201);
    }

    public function show(Video $video): JsonResponse
    {
        $this->authorize('view', $video);

        return ApiResponse::success(new VideoResource($video));
    }

    public function update(UpdateVideoRequest $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        return ApiResponse::success(new VideoResource($this->videos->update($video, $request->validated())));
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
}
