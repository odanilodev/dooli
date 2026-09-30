<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Lesson;
use App\Services\StudentCourseService;
use App\Services\VideoProgressService;
use App\Services\VideoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends ApiController
{
    public function __construct(
        private readonly StudentCourseService $studentCourses,
        private readonly VideoService $videos,
        private readonly VideoProgressService $videoProgress,
    ) {}

    public function playback(Request $request, int $course, int $lesson): JsonResponse
    {
        $team = $this->studentCourses->assertLessonInCourse($request->user(), $course, $lesson);

        $lessonModel = Lesson::query()->with('video')->findOrFail($lesson);

        $this->studentCourses->assertLessonUnlocked($request->user(), $lessonModel, $course, $team->id);

        abort_unless($lessonModel->video, 404, 'Aula sem vídeo.');

        $data = $this->videos->playback($lessonModel->video->id);
        $data['resume_from'] = $this->videoProgress->positionFor($request->user(), $lesson, $course, $team->id);

        return ApiResponse::success($data);
    }
}
