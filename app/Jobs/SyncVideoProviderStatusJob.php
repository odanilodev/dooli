<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\VideoService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Polls the video provider until the upload/import finishes encoding, then
 * fills the duration and poster automatically. Runs on the queue so the
 * admin request returns immediately.
 */
class SyncVideoProviderStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    /** @var array<int, int> */
    public array $backoff = [15, 30, 60, 120, 300];

    public function __construct(public int $videoId) {}

    public function handle(VideoService $videos): void
    {
        $context = app(CompanyContext::class);
        $previous = $context->id();

        $video = Video::query()->withoutGlobalScopes()->find($this->videoId);

        if (! $video) {
            return;
        }

        $context->set($video->company_id);

        try {
            $video = $videos->syncStatus($video->id);
        } finally {
            $previous === null ? $context->forget() : $context->set($previous);
        }

        if ($video->provider_status !== 'ready' && $this->attempts() < $this->tries) {
            $this->release(60);
        }
    }
}
