<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\GeneralStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\BulkModuleRequest;
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

    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Module::class);

        $paginator = $this->modules->tree(
            filters: $request->only(['search', 'usage']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, ModuleResource::class);
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $this->authorize('create', Module::class);

        $data = $request->validated();
        unset($data['image_file']);

        $module = $this->modules->create($data, $request->file('image_file'));

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

        $data = $request->validated();
        unset($data['image_file']);

        return ApiResponse::success(new ModuleResource($this->modules->update($module, $data, $request->file('image_file'))));
    }

    public function destroy(Module $module): JsonResponse
    {
        $this->authorize('delete', $module);

        $this->modules->delete($module);

        return ApiResponse::noContent();
    }

    public function bulk(BulkModuleRequest $request): JsonResponse
    {
        $this->authorize('create', Module::class);

        $validated = $request->validated();

        $summary = match ($validated['action']) {
            'activate' => $this->modules->bulkSetStatus($validated['module_ids'], GeneralStatus::Active->value),
            'deactivate' => $this->modules->bulkSetStatus($validated['module_ids'], GeneralStatus::Inactive->value),
            'delete' => $this->modules->bulkDelete($validated['module_ids']),
        };

        return ApiResponse::success($summary);
    }
}
