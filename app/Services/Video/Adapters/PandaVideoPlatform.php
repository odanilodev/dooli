<?php

namespace App\Services\Video\Adapters;

use App\Models\Video;
use App\Services\Video\Contracts\VideoPlatformContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Panda Video adapter (real integration).
 *
 * The API key is read from company settings (video.panda_api_key) or env.
 * Playback URLs are signed/temporary and credentials are never returned.
 */
class PandaVideoPlatform implements VideoPlatformContract
{
    public function code(): string
    {
        return 'panda';
    }

    public function playbackData(Video $video): array
    {
        $apiKey = config('services.panda.api_key');

        if (! $apiKey) {
            return [
                'platform' => 'panda',
                'embed_url' => $video->link,
                'video_code' => $video->code,
                'duration' => $video->time,
                'warning' => 'Panda API key ausente; retornando link direto.',
            ];
        }

        $cacheKey = 'panda:video:'.$video->id;

        $data = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($video, $apiKey) {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.$apiKey])
                ->acceptJson()
                ->get("https://api-v2.pandavideo.com.br/videos/{$video->code}");

            if ($response->failed()) {
                Log::warning('Panda video fetch failed', ['video_id' => $video->id, 'status' => $response->status()]);

                return [];
            }

            return $response->json();
        });

        return [
            'platform' => 'panda',
            'embed_url' => $data['player_url'] ?? $data['embed_url'] ?? $video->link,
            'video_code' => $video->code,
            'duration' => $data['length'] ?? $video->time,
            'thumbnail' => $data['thumbnail'] ?? $video->image,
        ];
    }
}
