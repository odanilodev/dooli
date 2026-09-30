<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MenuResource;
use App\Services\MenuService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyMenuController extends ApiController
{
    public function __construct(private readonly MenuService $menus) {}

    public function index(Request $request): JsonResponse
    {
        $menus = $this->menus->visibleFor($request->user());

        return ApiResponse::success(MenuResource::collection($menus));
    }
}
