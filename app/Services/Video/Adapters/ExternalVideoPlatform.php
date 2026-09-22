<?php

namespace App\Services\Video\Adapters;

use App\Models\Video;
use App\Services\Video\Contracts\VideoPlatformContract;

class ExternalVideoPlatform implements VideoPlatformContract
{
    public function code(): string
    {
        return 'external';
    }

    public function playbackData(Video $video): array
    {
        return [
            'platform' => 'external',
            'embed_url' => $video->link,
            'video_code' => $video->code,
            'duration' => $video->time,
        ];
    }
}
