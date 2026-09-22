<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Student\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Services\StudentCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends ApiController
{
    public function __construct(private readonly StudentCourseService $courses) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = Rating::query()
            ->with('user')
            ->whereIn('course_id', $this->courses->enrolledCourseIds($request->user()))
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->integer('lesson_id')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, RatingResource::class);
    }

    public function store(StoreRatingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->courses->assertLessonInCourse(
            $request->user(),
            (int) $data['course_id'],
            (int) $data['lesson_id'],
            (int) $data['team_id'],
        );

        $rating = Rating::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success(new RatingResource($rating->load('user')), 201);
    }
}
