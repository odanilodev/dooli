<?php

namespace App\Services\Video\Contracts;

use App\Models\Video;

interface VideoPlatformContract
{
    public function code(): string;

    /**
     * Returns safe playback data for a lesson video.
     * Credentials must never be exposed.
     *
     * @return array<string, mixed>
     */
    public function playbackData(Video $video): array;
}
