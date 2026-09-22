<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreGatewayRequest;
use App\Http\Requests\Admin\UpdateGatewayRequest;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use App\Services\GatewayService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatewayController extends ApiController
{
    public function __construct(private readonly GatewayService $gateways) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Gateway::class);

        $paginator = $this->gateways->paginate(
            filters: $request->only(['search', 'status', 'code', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, GatewayResource::class);
    }

    public function store(StoreGatewayRequest $request): JsonResponse
    {
        $this->authorize('create', Gateway::class);

        $gateway = $this->gateways->create($request->validated());

        return ApiResponse::success(new GatewayResource($gateway), 201);
    }

    public function show(Gateway $gateway): JsonResponse
    {
        $this->authorize('view', $gateway);

        return ApiResponse::success(new GatewayResource($gateway));
    }

    public function update(UpdateGatewayRequest $request, Gateway $gateway): JsonResponse
    {
        $this->authorize('update', $gateway);

        return ApiResponse::success(new GatewayResource($this->gateways->update($gateway, $request->validated())));
    }

    public function destroy(Gateway $gateway): JsonResponse
    {
        $this->authorize('delete', $gateway);

        $this->gateways->delete($gateway);

        return ApiResponse::noContent();
    }
}
