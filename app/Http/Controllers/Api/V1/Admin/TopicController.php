<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ReorderRequest;
use App\Http\Requests\Admin\StoreTopicRequest;
use App\Http\Requests\Admin\UpdateTopicRequest;
use App\Http\Resources\TopicResource;
use App\Models\Module;
use App\Models\Topic;
use App\Services\TopicService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopicController extends ApiController
{
    public function __construct(private readonly TopicService $topics) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Topic::class);

        $paginator = $this->topics->paginate(
            filters: $request->only(['search', 'status', 'module_id', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, TopicResource::class);
    }

    public function store(StoreTopicRequest $request): JsonResponse
    {
        $this->authorize('create', Topic::class);

        $topic = $this->topics->create($request->validated());

        return ApiResponse::success(new TopicResource($topic), 201);
    }

    public function show(Topic $topic): JsonResponse
    {
        $this->authorize('view', $topic);

        return ApiResponse::success(new TopicResource($this->topics->find($topic->id)));
    }

    public function update(UpdateTopicRequest $request, Topic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        return ApiResponse::success(new TopicResource($this->topics->update($topic, $request->validated())));
    }

    public function destroy(Topic $topic): JsonResponse
    {
        $this->authorize('delete', $topic);

        $this->topics->delete($topic);

        return ApiResponse::noContent();
    }

    public function reorder(ReorderRequest $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $this->topics->reorder($module, $request->validated('ids'));

        return ApiResponse::success(['message' => 'Tópicos reordenados.']);
    }
}
