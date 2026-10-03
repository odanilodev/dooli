<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTO\CreateCourseDTO;
use App\DTO\UpdateCourseDTO;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ReorderRequest;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\CourseService;
use App\Services\ModuleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends ApiController
{
    public function __construct(
        private readonly CourseService $courses,
        private readonly ModuleService $modules,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        $paginator = $this->courses->paginate(
            filters: $request->only(['search', 'status', 'sort', 'direction']),
            perPage: $this->perPage($request),
            with: ['modules'],
        );

        return ApiResponse::paginated($paginator, CourseResource::class);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        $data = $request->validated();
        unset($data['image_file']);

        $course = $this->courses->create(CreateCourseDTO::fromArray($data), $request->file('image_file'));

        return ApiResponse::success(new CourseResource($course), 201);
    }

    public function show(Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        return ApiResponse::success(new CourseResource($this->courses->findWithContent($course->id)));
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validated();
        unset($data['image_file']);

        $updated = $this->courses->update(
            $course,
            UpdateCourseDTO::fromArray($data),
            $request->file('image_file'),
            $request->has('image'),
        );

        return ApiResponse::success(new CourseResource($updated));
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        $this->courses->delete($course);

        return ApiResponse::noContent();
    }

    public function publish(Course $course): JsonResponse
    {
        $this->authorize('publish', $course);

        return ApiResponse::success(new CourseResource($this->courses->publish($course)));
    }

    public function unpublish(Course $course): JsonResponse
    {
        $this->authorize('publish', $course);

        return ApiResponse::success(new CourseResource($this->courses->unpublish($course)));
    }

    public function attachModule(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->modules->attachToCourse($course, (int) $validated['module_id'], (int) ($validated['sort_order'] ?? 0));

        return ApiResponse::success(['message' => 'Módulo associado ao curso.']);
    }

    public function detachModule(Course $course, int $module): JsonResponse
    {
        $this->authorize('update', $course);

        $this->modules->detachFromCourse($course, $module);

        return ApiResponse::success(['message' => 'Módulo desassociado do curso.']);
    }

    public function reorderModules(ReorderRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $cleared = $this->modules->reorder($course, $request->validated('ids'), $request->boolean('force'));

        return ApiResponse::success(['message' => 'Módulos reordenados.', 'cleared' => $cleared]);
    }
}
