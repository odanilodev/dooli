<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreModuleRequest;
use App\Http\Requests\Admin\UpdateModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Services\ModuleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends ApiController
{
    public function __construct(private readonly ModuleService $modules) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Module::class);

        $paginator = $this->modules->paginate(
            filters: $request->only(['search', 'status', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, ModuleResource::class);
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $this->authorize('create', Module::class);

        $module = $this->modules->create($request->validated());

        return ApiResponse::success(new ModuleResource($module), 201);
    }

    public function show(Module $module): JsonResponse
    {
        $this->authorize('view', $module);

        return ApiResponse::success(new ModuleResource($this->modules->find($module->id)));
    }

    public function update(UpdateModuleRequest $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        return ApiResponse::success(new ModuleResource($this->modules->update($module, $request->validated())));
    }

    public function destroy(Module $module): JsonResponse
    {
        $this->authorize('delete', $module);

        $this->modules->delete($module);

        return ApiResponse::noContent();
    }
}
