<?php

namespace App\Services\Video\Adapters;

use App\Models\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Panda Video adapter (real integration).
 *
 * The API key comes from the company's active "video_platforms" record
 * (code = panda, credentials.api_key). Playback URLs are temporary and
 * credentials are never returned.
 */
class PandaVideoPlatform extends AbstractVideoPlatform
{
    public function code(): string
    {
        return 'panda';
    }

    public function playbackData(Video $video): array
    {
        $apiKey = $this->credential('api_key');

        if (! $apiKey) {
            return [
                'platform' => 'panda',
                'embed_url' => $video->link,
                'video_code' => $video->code,
                'duration' => $video->time,
                'warning' => 'Panda sem api_key configurada; retornando link direto.',
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
