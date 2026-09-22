<?php

namespace App\Services;

use App\Models\Video;
use App\Repositories\VideoRepository;
use App\Services\Video\VideoPlatformManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VideoService
{
    public function __construct(
        private readonly VideoRepository $videos,
        private readonly VideoPlatformManager $platforms,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->videos->paginate(
            perPage: $perPage,
            filters: $filters,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): Video
    {
        return $this->videos->findOrFail($id);
    }

    public function create(array $data): Video
    {
        $video = $this->videos->create($data);

        $this->audit->log('video', 'create', 'video', "Vídeo criado: {$video->title}");

        return $video;
    }

    public function update(Video $video, array $data): Video
    {
        return $this->videos->update($video, $data);
    }

    public function delete(Video $video): void
    {
        $video->delete();

        $this->audit->log('video', 'delete', 'video', "Vídeo excluído: {$video->title}");
    }

    /**
     * @return array<string, mixed>
     */
    public function playback(int $id): array
    {
        $video = $this->videos->findOrFail($id);

        return $this->platforms->for($video)->playbackData($video);
    }
}
