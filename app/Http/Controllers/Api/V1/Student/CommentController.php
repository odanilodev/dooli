<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Student\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Lesson;
use App\Services\StudentCourseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommentController extends ApiController
{
    public function __construct(private readonly StudentCourseService $courses) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = Comment::query()
            ->with(['user.userType', 'replies.user.userType'])
            ->whereNull('parent_id')
            ->whereIn('course_id', $this->courses->enrolledCourseIds($request->user()))
            ->whereHas('lesson', fn ($q) => $q->where('comments_enabled', true))
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->integer('lesson_id')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, CommentResource::class);
    }

    public function store(StoreCommentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['parent_id'])) {
            return $this->storeReply($request, $data);
        }

        $this->courses->assertLessonInCourse(
            $request->user(),
            (int) $data['course_id'],
            (int) $data['lesson_id'],
            (int) $data['team_id'],
        );

        $commentsEnabled = Lesson::query()
            ->whereKey((int) $data['lesson_id'])
            ->value('comments_enabled');

        abort_if(! $commentsEnabled, 403, 'Os comentários estão desativados para esta aula.');

        $comment = Comment::query()->create([
            ...$data,
            'parent_id' => null,
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success(new CommentResource($comment->load('user.userType')), 201);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeReply(StoreCommentRequest $request, array $data): JsonResponse
    {
        $parent = Comment::query()
            ->with('lesson')
            ->findOrFail((int) $data['parent_id']);

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Só é possível responder a um comentário principal.',
            ]);
        }

        abort_if(! $parent->lesson?->comments_enabled, 403, 'Os comentários estão desativados para esta aula.');
        abort_if(! $parent->lesson?->student_replies_enabled, 403, 'As respostas de alunos estão desativadas para esta aula.');

        $this->courses->assertLessonInCourse(
            $request->user(),
            (int) $parent->course_id,
            (int) $parent->lesson_id,
            (int) $parent->team_id,
        );

        $reply = Comment::query()->create([
            'company_id' => $parent->company_id,
            'content' => $data['content'],
            'lesson_id' => $parent->lesson_id,
            'module_id' => $parent->module_id,
            'course_id' => $parent->course_id,
            'team_id' => $parent->team_id,
            'user_id' => $request->user()->id,
            'parent_id' => $parent->id,
            'image' => $data['image'] ?? null,
        ]);

        return ApiResponse::success(new CommentResource($reply->load('user.userType')), 201);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('deleteOwn', $comment);

        $comment->delete();

        return ApiResponse::noContent();
    }
}
