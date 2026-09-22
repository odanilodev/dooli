<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Student\GenerateCertificateRequest;
use App\Http\Resources\CertificateUserResource;
use App\Services\CertificateService;
use App\Services\StudentCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends ApiController
{
    public function __construct(
        private readonly CertificateService $certificates,
        private readonly StudentCourseService $studentCourses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::collection(
            CertificateUserResource::collection($this->certificates->listForUser($request->user())),
        );
    }

    public function generate(GenerateCertificateRequest $request): JsonResponse
    {
        $user = $request->user();
        $courseId = (int) $request->validated('course_id');
        $teamId = (int) $request->validated('team_id');

        // Ensures the student actually has access to the course/team.
        $this->studentCourses->assertAccess($user, $courseId, $teamId);

        $certificate = $this->certificates->generate($user, $courseId, $teamId);

        return ApiResponse::success(new CertificateUserResource($certificate), 201);
    }
}
