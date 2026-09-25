<?php

namespace App\Services\Video\Adapters;

use App\Models\Video;
use App\Models\VideoPlatform;
use App\Repositories\VideoPlatformRepository;
use App\Services\Video\Contracts\VideoPlatformContract;

/**
 * Base adapter. Resolves the platform credentials/settings from the
 * per-company "video_platforms" table so any provider can be configured
 * without code changes. Credentials are never exposed to the client.
 */
abstract class AbstractVideoPlatform implements VideoPlatformContract
{
    public function __construct(protected readonly VideoPlatformRepository $platforms) {}

    /**
     * Default playback used when a platform has no real integration yet.
     *
     * @return array<string, mixed>
     */
    public function playbackData(Video $video): array
    {
        return [
            'platform' => $this->code(),
            'embed_url' => $video->link,
            'video_code' => $video->code,
            'duration' => $video->time,
            'warning' => "Integração '{$this->code()}' ainda não implementada; retornando link direto.",
        ];
    }

    protected function config(): ?VideoPlatform
    {
        return $this->platforms->findActiveByCode($this->code());
    }

    protected function credential(string $key, mixed $default = null): mixed
    {
        return $this->config()?->credential($key, $default) ?? $default;
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->config()?->settings[$key] ?? $default;
    }
}
