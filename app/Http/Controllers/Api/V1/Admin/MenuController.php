<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreMenuRequest;
use App\Http\Requests\Admin\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MenuController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(MenuResource::collection($menus));
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        $this->authorize('create', Menu::class);

        $menu = Menu::query()->create($request->validated());

        return ApiResponse::success(new MenuResource($menu), 201);
    }

    public function update(UpdateMenuRequest $request, Menu $menu): JsonResponse
    {
        $this->authorize('update', $menu);

        $menu->update($request->validated());

        return ApiResponse::success(new MenuResource($menu));
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->authorize('delete', $menu);

        Menu::query()->where('sub', (string) $menu->id)->update(['sub' => null]);

        $menu->delete();

        return ApiResponse::noContent();
    }
}
