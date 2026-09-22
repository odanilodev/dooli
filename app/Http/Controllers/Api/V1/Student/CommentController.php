<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Student\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Services\StudentCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends ApiController
{
    public function __construct(private readonly StudentCourseService $courses) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = Comment::query()
            ->with('user')
            ->whereIn('course_id', $this->courses->enrolledCourseIds($request->user()))
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->integer('lesson_id')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, CommentResource::class);
    }

    public function store(StoreCommentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->courses->assertLessonInCourse(
            $request->user(),
            (int) $data['course_id'],
            (int) $data['lesson_id'],
            (int) $data['team_id'],
        );

        $comment = Comment::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success(new CommentResource($comment->load('user')), 201);
    }
}
