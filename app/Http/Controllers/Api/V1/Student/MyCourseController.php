<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\CourseResource;
use App\Http\Resources\StudentCourseResource;
use App\Models\Course;
use App\Services\ProgressService;
use App\Services\StudentCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCourseController extends ApiController
{
    public function __construct(
        private readonly StudentCourseService $studentCourses,
        private readonly ProgressService $progress,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $paginator = $this->studentCourses->myCourses($user, $this->perPage($request));

        $summary = $this->progress->progressSummaryFor($user, $paginator->getCollection());

        $paginator->getCollection()->each(
            fn (Course $course) => $course->setAttribute('progress', $summary[$course->id] ?? null),
        );

        return ApiResponse::paginated($paginator, StudentCourseResource::class);
    }

    public function show(Request $request, int $course): JsonResponse
    {
        $course = $this->studentCourses->courseContent($request->user(), $course);

        return ApiResponse::success(new CourseResource($course));
    }

    public function progress(Request $request, int $course): JsonResponse
    {
        $teamId = (int) $request->integer('team_id');
        $team = $this->studentCourses->assertAccess($request->user(), $course, $teamId ?: null);

        return ApiResponse::success($this->progress->progressFor($request->user(), $course, $team->id));
    }

    public function timeline(Request $request, int $course): JsonResponse
    {
        $teamId = (int) $request->integer('team_id');
        $team = $this->studentCourses->assertAccess($request->user(), $course, $teamId ?: null);

        return ApiResponse::success($this->progress->history($request->user(), $course, $team->id));
    }

    public function continueWatching(Request $request): JsonResponse
    {
        return ApiResponse::success($this->progress->continueWatching($request->user()));
    }
}
