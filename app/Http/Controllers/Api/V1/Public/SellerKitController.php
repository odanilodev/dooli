<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\GeneralStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\SellerKitResource;
use App\Models\Company;
use App\Models\StoreProduct;
use App\Repositories\SellerRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerKitController extends ApiController
{
    public function __construct(private readonly SellerRepository $sellers) {}

    public function show(Request $request): JsonResponse
    {
        $token = (string) $request->route('token');
        $seller = $this->sellers->findActiveByShareToken($token);

        abort_unless($seller, 404, 'Kit de vendedor não encontrado.');

        /** @var Company $company */
        $company = $seller->company;

        $products = StoreProduct::query()
            ->where('status', GeneralStatus::Active->value)
            ->released()
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return ApiResponse::success(new SellerKitResource($seller, $products, $company));
    }
}
