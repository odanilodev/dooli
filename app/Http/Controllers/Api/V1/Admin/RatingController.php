<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Rating::class);

        $paginator = Rating::query()
            ->with('user')
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->integer('lesson_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, RatingResource::class);
    }

    public function destroy(Rating $rating): JsonResponse
    {
        $this->authorize('delete', $rating);

        $rating->delete();

        return ApiResponse::noContent();
    }
}
