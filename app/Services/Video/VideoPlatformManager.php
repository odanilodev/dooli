<?php

namespace App\Services\Video;

use App\Models\Video;
use App\Services\Video\Adapters\ExternalVideoPlatform;
use App\Services\Video\Adapters\FastPixVideoPlatform;
use App\Services\Video\Adapters\PandaVideoPlatform;
use App\Services\Video\Adapters\VdoCipherVideoPlatform;
use App\Services\Video\Adapters\VimeoVideoPlatform;
use App\Services\Video\Contracts\VideoPlatformContract;

/**
 * Resolves the video platform adapter by the video's "player" code.
 */
class VideoPlatformManager
{
    /** @var array<string, class-string<VideoPlatformContract>> */
    protected array $adapters = [
        'panda' => PandaVideoPlatform::class,
        'vdocipher' => VdoCipherVideoPlatform::class,
        'vimeo' => VimeoVideoPlatform::class,
        'fastpix' => FastPixVideoPlatform::class,
        'external' => ExternalVideoPlatform::class,
    ];

    public function for(Video $video): VideoPlatformContract
    {
        return $this->make($video->player ?: 'external');
    }

    public function make(string $code): VideoPlatformContract
    {
        $class = $this->adapters[$code] ?? ExternalVideoPlatform::class;

        return app($class);
    }
}
