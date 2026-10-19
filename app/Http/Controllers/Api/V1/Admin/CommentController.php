<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreCommentReplyRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Comment::class);

        $paginator = Comment::query()
            ->with(['user.userType', 'lesson'])
            ->when($request->boolean('roots_only'), function ($query) {
                $query->whereNull('parent_id')->with('replies.user.userType');
            })
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->integer('lesson_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, CommentResource::class);
    }

    public function store(StoreCommentReplyRequest $request): JsonResponse
    {
        $this->authorize('create', Comment::class);

        $data = $request->validated();

        $parent = Comment::query()
            ->with('lesson')
            ->findOrFail((int) $data['parent_id']);

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Só é possível responder a um comentário principal.',
            ]);
        }

        abort_if(! $parent->lesson?->comments_enabled, 403, 'Os comentários estão desativados para esta aula.');

        $reply = Comment::query()->create([
            'company_id' => $parent->company_id,
            'content' => $data['content'],
            'lesson_id' => $parent->lesson_id,
            'module_id' => $parent->module_id,
            'course_id' => $parent->course_id,
            'team_id' => $parent->team_id,
            'user_id' => $request->user()->id,
            'parent_id' => $parent->id,
        ]);

        return ApiResponse::success(new CommentResource($reply->load('user.userType')), 201);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return ApiResponse::noContent();
    }
}
