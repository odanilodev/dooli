<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\GeneralStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\StoreProductResource;
use App\Repositories\StoreProductRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends ApiController
{
    public function __construct(private readonly StoreProductRepository $storeProducts) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->storeProducts->paginateReleased(
            perPage: $this->perPage($request),
            filters: [
                'status' => GeneralStatus::Active->value,
                ...$request->only(['search']),
            ],
            with: ['course'],
        );

        return ApiResponse::paginated($paginator, StoreProductResource::class);
    }

    public function show(string $slug): JsonResponse
    {
        $storeProduct = $this->storeProducts->findReleasedBySlug($slug);

        abort_unless($storeProduct, 404, 'Produto não encontrado.');

        return ApiResponse::success(new StoreProductResource($storeProduct->load('course')));
    }
}
