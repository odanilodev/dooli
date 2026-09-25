<?php

namespace App\Services;

use App\Models\VideoPlatform;
use App\Repositories\VideoPlatformRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VideoPlatformService
{
    public function __construct(
        private readonly VideoPlatformRepository $platforms,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->platforms->paginate(
            perPage: $perPage,
            filters: $filters,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): VideoPlatform
    {
        return $this->platforms->findOrFail($id);
    }

    public function create(array $data): VideoPlatform
    {
        $platform = $this->platforms->create($data);

        $this->audit->log('video_platform', 'create', 'video_platform', "Plataforma de vídeo criada: {$platform->name}");

        return $platform;
    }

    public function update(VideoPlatform $platform, array $data): VideoPlatform
    {
        // Never overwrite credentials with an empty value.
        if (array_key_exists('credentials', $data) && empty($data['credentials'])) {
            unset($data['credentials']);
        }

        return $this->platforms->update($platform, $data);
    }

    public function delete(VideoPlatform $platform): void
    {
        $platform->delete();

        $this->audit->log('video_platform', 'delete', 'video_platform', "Plataforma de vídeo excluída: {$platform->name}");
    }
}
