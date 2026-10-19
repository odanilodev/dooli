<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyLinkController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        return ApiResponse::success([
            'slug' => $company?->slug,
            'public_store_url' => $company?->publicStoreUrl(),
        ]);
    }
}
