<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Repositories\CompanyRepository;
use App\Services\CompanyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly CompanyService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $paginator = $this->companies->paginate(
            perPage: $this->perPage($request),
            filters: $request->only(['search', 'status', 'sort', 'direction']),
        );

        return ApiResponse::paginated($paginator, CompanyResource::class);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $result = $this->service->create($request->validated());

        return ApiResponse::success(new CompanyResource($result['company']), 201);
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        return ApiResponse::success(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        return ApiResponse::success(new CompanyResource($this->service->update($company, $request->validated())));
    }
}
