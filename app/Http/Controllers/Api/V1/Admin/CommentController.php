<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserTypeCode;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreCommentReplyRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Comment::class);

        $status = $request->string('status')->toString();

        $paginator = Comment::query()
            ->with(['user.userType', 'course', 'module', 'lesson'])
            ->when($request->boolean('roots_only'), function (Builder $query) {
                $query->whereNull('parent_id')->with('replies.user.userType');
            })
            ->when($request->filled('lesson_id'), fn (Builder $q) => $q->where('lesson_id', $request->integer('lesson_id')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('course_id'), fn (Builder $q) => $q->where('course_id', $request->integer('course_id')))
            ->when($status === 'awaiting', fn (Builder $q) => $this->whereAwaitingAdminReply($q->whereNull('parent_id')))
            ->when($status === 'answered', fn (Builder $q) => $this->whereHasAdminReply($q->whereNull('parent_id')))
            ->withCount([
                'replies',
                'replies as admin_replies_count' => fn (Builder $q) => $this->whereAdminReply($q),
            ])
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        $meta = [];

        if ($request->boolean('roots_only') || $status !== '') {
            $stats = $this->rootStats($request);

            $meta['stats'] = [
                ...$stats,
                'answered' => max(0, $stats['total'] - $stats['awaiting']),
            ];
        }

        return ApiResponse::paginated($paginator, CommentResource::class, $meta);
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

    public function destroyMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $comments = Comment::query()
            ->whereIn('id', $data['ids'])
            ->get();

        abort_if($comments->count() !== count($data['ids']), 404, 'Recurso não encontrado.');

        $comments->each(fn (Comment $comment) => $this->authorize('delete', $comment));

        $deleted = DB::transaction(function () use ($comments) {
            $comments->each->delete();

            return $comments->count();
        });

        return ApiResponse::success(['deleted' => $deleted]);
    }

    /**
     * @return array{total: int, awaiting: int}
     */
    private function rootStats(Request $request): array
    {
        $query = Comment::query()
            ->whereNull('parent_id')
            ->when($request->filled('lesson_id'), fn (Builder $q) => $q->where('lesson_id', $request->integer('lesson_id')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('course_id'), fn (Builder $q) => $q->where('course_id', $request->integer('course_id')));

        return [
            'total' => (clone $query)->count(),
            'awaiting' => $this->whereAwaitingAdminReply(clone $query)->count(),
        ];
    }

    private function whereAwaitingAdminReply(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'replies',
            fn (Builder $replies) => $this->whereAdminReply($replies),
        );
    }

    private function whereHasAdminReply(Builder $query): Builder
    {
        return $query->whereHas(
            'replies',
            fn (Builder $replies) => $this->whereAdminReply($replies),
        );
    }

    private function whereAdminReply(Builder $query): Builder
    {
        return $query->whereHas(
            'user.userType',
            fn (Builder $userType) => $userType->where('code', UserTypeCode::Admin->value),
        );
    }
}
