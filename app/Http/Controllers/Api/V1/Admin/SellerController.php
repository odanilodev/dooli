<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreSellerRequest;
use App\Http\Requests\Admin\UpdateSellerRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\SellerResource;
use App\Models\Seller;
use App\Services\SellerReportService;
use App\Services\SellerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerController extends ApiController
{
    public function __construct(
        private readonly SellerService $sellers,
        private readonly SellerReportService $reports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Seller::class);

        $paginator = $this->reports->paginate(
            filters: $request->only(['search', 'status', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, SellerResource::class);
    }

    public function store(StoreSellerRequest $request): JsonResponse
    {
        $this->authorize('create', Seller::class);

        $seller = $this->sellers->create($request->validated());

        return ApiResponse::success(new SellerResource($seller), 201);
    }

    public function show(Seller $seller): JsonResponse
    {
        $this->authorize('view', $seller);

        $detail = $this->reports->detail($seller);

        return ApiResponse::success([
            'seller' => new SellerResource($detail['seller']),
            'metrics' => $detail['metrics'],
            'products' => $detail['products'],
            'recent_orders' => OrderResource::collection($detail['recent_orders']),
        ]);
    }

    public function update(UpdateSellerRequest $request, Seller $seller): JsonResponse
    {
        $this->authorize('update', $seller);

        return ApiResponse::success(
            new SellerResource($this->sellers->update($seller, $request->validated())),
        );
    }

    public function destroy(Seller $seller): JsonResponse
    {
        $this->authorize('delete', $seller);

        $this->sellers->delete($seller);

        return ApiResponse::noContent();
    }

    public function rotateKit(Seller $seller): JsonResponse
    {
        $this->authorize('update', $seller);

        return ApiResponse::success(
            new SellerResource($this->sellers->rotateKitToken($seller)),
        );
    }
}
