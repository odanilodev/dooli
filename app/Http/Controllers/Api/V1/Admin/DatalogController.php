<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\DatalogResource;
use App\Models\Datalog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatalogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Datalog::class);

        $paginator = Datalog::query()
            ->with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, DatalogResource::class);
    }
}
