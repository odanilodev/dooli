<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\StudentTeamResource;
use App\Services\StudentCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends ApiController
{
    public function __construct(private readonly StudentCourseService $studentCourses) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            StudentTeamResource::collection($this->studentCourses->myTeams($request->user())),
        );
    }
}
