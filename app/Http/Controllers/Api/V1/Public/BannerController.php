<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\BannerPlacement;
use App\Enums\GeneralStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BannerController extends ApiController
{
    public function index(): JsonResponse
    {
        $banners = Banner::query()
            ->where('placement', BannerPlacement::Home->value)
            ->where('status', GeneralStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(BannerResource::collection($banners));
    }
}
