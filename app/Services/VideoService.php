<?php

namespace App\Services;

use App\Enums\VideoPlayer;
use App\Jobs\SyncVideoProviderStatusJob;
use App\Models\Video;
use App\Repositories\VideoRepository;
use App\Services\Video\VideoPlatformManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        if ($video->player && $video->code) {
            try {
                $this->platforms->make($video->player)->delete($video->code);
            } catch (Throwable $e) {
                Log::warning('Video provider delete failed', [
                    'video_id' => $video->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

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

    /**
     * Asks the provider to pull a video from a remote URL. The record is only
     * created after the provider accepts it, so a failed import persists nothing.
     */
    public function importFromUrl(array $data): Video
    {
        $result = $this->platforms
            ->make(VideoPlayer::VdoCipher->value)
            ->importFromUrl($data['url'], $data['title'], $data['folder_id'] ?? null);

        $attributes = [
            'title' => $data['title'],
            'player' => VideoPlayer::VdoCipher->value,
            'code' => $result['video_code'],
            'provider_status' => $result['status'] ?? 'Queued',
        ];

        if (! empty($data['status'])) {
            $attributes['status'] = $data['status'];
        }

        $video = $this->videos->create($attributes);

        $this->audit->log('video', 'import', 'video', "Vídeo importado no VdoCipher: {$video->title}");
        SyncVideoProviderStatusJob::dispatch($video->id);

        return $video;
    }

    /**
     * Requests temporary upload credentials and creates a pending video record.
     *
     * @return array{video: Video, upload: array<string, mixed>}
     */
    public function createUploadCredentials(array $data): array
    {
        $result = $this->platforms
            ->make(VideoPlayer::VdoCipher->value)
            ->createUploadCredentials($data['title'], $data['folder_id'] ?? null);

        $attributes = [
            'title' => $data['title'],
            'player' => VideoPlayer::VdoCipher->value,
            'code' => $result['video_code'],
            'provider_status' => 'Pre-Upload',
        ];

        if (! empty($data['status'])) {
            $attributes['status'] = $data['status'];
        }

        $video = $this->videos->create($attributes);

        $this->audit->log('video', 'upload', 'video', "Upload no VdoCipher iniciado: {$video->title}");

        return ['video' => $video, 'upload' => $result['upload']];
    }

    /**
     * Refreshes the provider status; when ready, fills duration and poster.
     */
    public function syncStatus(int $id): Video
    {
        $video = $this->videos->findOrFail($id);

        if (! $video->code || $video->player !== VideoPlayer::VdoCipher->value) {
            return $video;
        }

        $result = $this->platforms->make($video->player)->status($video->code);
        $status = $this->normalizeProviderStatus($result['status']);

        $data = ['provider_status' => $status];

        if ($status === 'ready') {
            if (! empty($result['duration'])) {
                $data['time'] = (int) $result['duration'];
            }

            if (! empty($result['thumbnail'])) {
                $data['image'] = $result['thumbnail'];
            }
        }

        return $this->videos->update($video, $data);
    }

    /**
     * Manually refreshes every non-ready VdoCipher video of the company
     * (temporary "Atualizar" button). Failures don't block the batch.
     *
     * @return array{checked: int, ready: int, pending: int, failed: int}
     */
    public function syncMany(): array
    {
        $videos = $this->videos->query()
            ->where('player', VideoPlayer::VdoCipher->value)
            ->whereNotNull('code')
            ->where(function ($query) {
                $query->whereNull('provider_status')->orWhere('provider_status', '!=', 'ready');
            })
            ->get();

        $summary = ['checked' => 0, 'ready' => 0, 'pending' => 0, 'failed' => 0];

        foreach ($videos as $video) {
            $summary['checked']++;

            try {
                $updated = $this->syncStatus($video->id);
                $summary[$updated->provider_status === 'ready' ? 'ready' : 'pending']++;
            } catch (Throwable $e) {
                $summary['failed']++;

                Log::warning('Video status refresh failed', [
                    'video_id' => $video->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * The provider returns status with inconsistent casing (e.g. "PRE-Upload").
     */
    private function normalizeProviderStatus(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return $status;
        }

        return strtolower(trim($status));
    }
}
