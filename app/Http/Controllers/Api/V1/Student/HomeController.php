<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\StudentHomeTeamResource;
use App\Services\StudentHomeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends ApiController
{
    public function __construct(private readonly StudentHomeService $home) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            StudentHomeTeamResource::collection($this->home->forUser($request->user())),
        );
    }
}
