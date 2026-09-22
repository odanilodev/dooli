<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\CourseResource;
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
        $paginator = $this->studentCourses->myCourses($request->user(), $this->perPage($request));

        return ApiResponse::paginated($paginator, CourseResource::class);
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
}
