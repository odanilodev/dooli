<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ReorderRequest;
use App\Http\Requests\Admin\StoreLessonRequest;
use App\Http\Requests\Admin\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\Topic;
use App\Services\LessonService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends ApiController
{
    public function __construct(private readonly LessonService $lessons) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lesson::class);

        $paginator = $this->lessons->paginate(
            filters: $request->only(['search', 'status', 'topic_id', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, LessonResource::class);
    }

    public function store(StoreLessonRequest $request): JsonResponse
    {
        $this->authorize('create', Lesson::class);

        $data = $request->safe()->except('attachment_ids');
        $lesson = $this->lessons->create($data);

        if ($request->filled('attachment_ids')) {
            $lesson->attachments()->sync($this->pivotAttachments($lesson, $request->validated('attachment_ids')));
        }

        return ApiResponse::success(new LessonResource($lesson), 201);
    }

    public function show(Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $lesson);

        return ApiResponse::success(new LessonResource($this->lessons->find($lesson->id)));
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        $updated = $this->lessons->update($lesson, $request->safe()->except('attachment_ids'));

        if ($request->has('attachment_ids')) {
            $updated->attachments()->sync($this->pivotAttachments($updated, $request->validated('attachment_ids') ?? []));
        }

        return ApiResponse::success(new LessonResource($updated));
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        $this->lessons->delete($lesson);

        return ApiResponse::noContent();
    }

    public function publish(Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        return ApiResponse::success(new LessonResource($this->lessons->publish($lesson)));
    }

    public function unpublish(Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        return ApiResponse::success(new LessonResource($this->lessons->unpublish($lesson)));
    }

    public function reorder(ReorderRequest $request, Topic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        $this->lessons->reorder($topic, $request->validated('ids'));

        return ApiResponse::success(['message' => 'Aulas reordenadas.']);
    }

    /**
     * @param  array<int, int>  $attachmentIds
     * @return array<int, array{company_id: int}>
     */
    private function pivotAttachments(Lesson $lesson, array $attachmentIds): array
    {
        return collect($attachmentIds)->mapWithKeys(fn ($id) => [
            $id => ['company_id' => $lesson->company_id],
        ])->all();
    }
}
