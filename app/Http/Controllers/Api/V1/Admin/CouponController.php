<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends ApiController
{
    public function __construct(private readonly CouponService $coupons) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $paginator = $this->coupons->paginate(
            filters: $request->only(['search', 'status', 'type', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, CouponResource::class);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        $coupon = $this->coupons->create($request->validated());

        return ApiResponse::success(new CouponResource($coupon->load('storeProducts')), 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $this->authorize('view', $coupon);

        return ApiResponse::success(new CouponResource($this->coupons->find($coupon->id)));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('update', $coupon);

        return ApiResponse::success(new CouponResource($this->coupons->update($coupon, $request->validated())->load('storeProducts')));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', $coupon);

        $this->coupons->delete($coupon);

        return ApiResponse::noContent();
    }
}
