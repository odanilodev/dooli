<?php

namespace App\Services\Video\Adapters;

use App\Models\Video;
use App\Services\Video\Contracts\VideoPlatformContract;

/**
 * Stub adapter. Extend with the platform's real SDK/HTTP calls.
 */
abstract class AbstractStubVideoPlatform implements VideoPlatformContract
{
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
}
