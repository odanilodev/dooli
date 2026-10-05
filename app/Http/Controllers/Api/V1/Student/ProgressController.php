<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\DTO\CompleteLessonDTO;
use App\DTO\SaveVideoPositionDTO;
use App\DTO\StartLessonDTO;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Student\CompleteLessonRequest;
use App\Http\Requests\Student\SaveVideoPositionRequest;
use App\Http\Requests\Student\StartLessonRequest;
use App\Services\ProgressService;
use App\Services\VideoProgressService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgressController extends ApiController
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly VideoProgressService $videoProgress,
    ) {}

    public function startLesson(StartLessonRequest $request): JsonResponse
    {
        $result = $this->progress->startLesson(
            $request->user(),
            StartLessonDTO::fromArray($request->validated()),
        );

        return ApiResponse::success($result);
    }

    public function completeLesson(CompleteLessonRequest $request): JsonResponse
    {
        $result = $this->progress->completeLesson(
            $request->user(),
            CompleteLessonDTO::fromArray($request->validated()),
        );

        return ApiResponse::success($result);
    }

    public function saveVideoPosition(SaveVideoPositionRequest $request): JsonResponse
    {
        $result = $this->videoProgress->save(
            $request->user(),
            SaveVideoPositionDTO::fromArray($request->validated()),
        );

        return ApiResponse::success($result);
    }
}
