<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\StudentLiveClassResource;
use App\Services\StudentLiveClassService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveClassController extends ApiController
{
    public function __construct(private readonly StudentLiveClassService $liveClasses) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            StudentLiveClassResource::collection($this->liveClasses->forUser($request->user())),
        );
    }
}
