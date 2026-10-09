<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Models\LiveClass;
use App\Repositories\LiveClassRepository;
use App\Support\CompanyContext;
use App\Support\ImageStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class LiveClassService
{
    public function __construct(
        private readonly LiveClassRepository $liveClasses,
        private readonly ImageStorage $images,
        private readonly CompanyContext $context,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->liveClasses->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['teams'],
            withCount: ['teams'],
        );
    }

    public function find(int $id): LiveClass
    {
        return $this->liveClasses->findWithRelations($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image = null): LiveClass
    {
        $companyId = (int) ($data['company_id'] ?? $this->context->id() ?? 0);
        $teamIds = $data['team_ids'] ?? [];
        unset($data['team_ids']);

        if ($image) {
            $data['image'] = $this->images->store($image, 'live-classes', $companyId);
        }

        $liveClass = $this->liveClasses->create($data);

        $this->syncTeams($liveClass, $teamIds);

        $this->audit->log('live-class', 'create', 'live_class', "Aula ao vivo criada: {$liveClass->name}");

        return $liveClass->load('teams');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LiveClass $liveClass, array $data, ?UploadedFile $image = null): LiveClass
    {
        if ($image) {
            $this->images->delete($liveClass->image);
            $data['image'] = $this->images->store($image, 'live-classes', (int) $liveClass->company_id);
        }

        if (array_key_exists('team_ids', $data)) {
            $this->syncTeams($liveClass, $data['team_ids'] ?? []);
        }

        unset($data['team_ids']);

        $liveClass = $this->liveClasses->update($liveClass, $data);

        $this->audit->log('live-class', 'update', 'live_class', "Aula ao vivo atualizada: {$liveClass->name}");

        return $liveClass->load('teams');
    }

    public function delete(LiveClass $liveClass): void
    {
        $this->images->delete($liveClass->image);
        $liveClass->teams()->detach();
        $this->liveClasses->delete($liveClass);

        $this->audit->log('live-class', 'delete', 'live_class', "Aula ao vivo excluída: {$liveClass->name}");
    }

    /**
     * @param  array<int, int|string>  $teamIds
     */
    private function syncTeams(LiveClass $liveClass, array $teamIds): void
    {
        $sync = [];

        foreach ($teamIds as $teamId) {
            $sync[(int) $teamId] = [
                'company_id' => $liveClass->company_id,
                'status' => GeneralStatus::Active->value,
            ];
        }

        $liveClass->teams()->sync($sync);
    }
}
