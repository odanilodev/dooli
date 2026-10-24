<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Models\StoreProduct;
use App\Models\TeamCourse;
use App\Repositories\StoreProductRepository;
use App\Support\CompanyContext;
use App\Support\ImageStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProductService
{
    public function __construct(
        private readonly StoreProductRepository $storeProducts,
        private readonly DatalogService $audit,
        private readonly ImageStorage $images,
        private readonly CompanyContext $context,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->storeProducts->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['course', 'teams.courses'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): StoreProduct
    {
        return $this->storeProducts->findWithTeams($id);
    }

    public function create(array $data, ?UploadedFile $image = null): StoreProduct
    {
        $teamIds = $data['team_ids'] ?? [];
        unset($data['team_ids']);

        $data['course_id'] = $this->resolveCourseId($teamIds);
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['image'] = $data['image'] ?? null;

        if ($image) {
            $data['image'] = $this->images->store($image, 'store-products', (int) $this->context->id());
        }

        $storeProduct = $this->storeProducts->create($data);

        $this->audit->log('store_product', 'create', 'store_product', "Produto de loja criado: {$storeProduct->title}");

        return $storeProduct;
    }

    public function update(
        StoreProduct $storeProduct,
        array $data,
        ?UploadedFile $image = null,
        bool $imageProvided = false,
    ): StoreProduct {
        if (isset($data['title']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        if ($image) {
            $this->images->delete($storeProduct->image);
            $data['image'] = $this->images->store($image, 'store-products', (int) $storeProduct->company_id);
        } elseif ($imageProvided && empty($data['image'])) {
            $this->images->delete($storeProduct->image);
            $data['image'] = null;
        } elseif (array_key_exists('image', $data) && $storeProduct->image !== $data['image']) {
            $this->images->delete($storeProduct->image);
        }

        return $this->storeProducts->update($storeProduct, $data);
    }

    public function delete(StoreProduct $storeProduct): void
    {
        DB::transaction(function () use ($storeProduct) {
            $storeProduct->teams()->detach();
            $storeProduct->delete();
        });

        $this->images->delete($storeProduct->image);

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

    private function resolveCourseId(array $teamIds): int
    {
        $courseId = TeamCourse::query()
            ->whereIn('team_id', $teamIds)
            ->where('status', GeneralStatus::Active->value)
            ->orderBy('team_id')
            ->orderBy('course_id')
            ->value('course_id');

        if (! $courseId) {
            throw ValidationException::withMessages([
                'team_ids' => 'Vincule ao menos um curso ativo às turmas selecionadas.',
            ]);
        }

        return (int) $courseId;
    }
}
