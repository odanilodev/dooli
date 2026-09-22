<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Models\StoreProduct;
use App\Repositories\StoreProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class StoreProductService
{
    public function __construct(
        private readonly StoreProductRepository $storeProducts,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->storeProducts->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['course'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): StoreProduct
    {
        return $this->storeProducts->findWithTeams($id);
    }

    public function create(array $data): StoreProduct
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);

        $storeProduct = $this->storeProducts->create($data);

        $this->audit->log('store_product', 'create', 'store_product', "Produto de loja criado: {$storeProduct->title}");

        return $storeProduct;
    }

    public function update(StoreProduct $storeProduct, array $data): StoreProduct
    {
        if (isset($data['title']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        return $this->storeProducts->update($storeProduct, $data);
    }

    public function delete(StoreProduct $storeProduct): void
    {
        $storeProduct->delete();

        $this->audit->log('store_product', 'delete', 'store_product', "Produto de loja excluído: {$storeProduct->title}");
    }

    public function syncTeams(StoreProduct $storeProduct, array $teamIds): void
    {
        $sync = collect($teamIds)->mapWithKeys(fn ($id) => [
            $id => [
                'company_id' => $storeProduct->company_id,
                'status' => GeneralStatus::Active->value,
            ],
        ])->all();

        $storeProduct->teams()->sync($sync);
    }
}
