<?php

namespace App\Services\Video\Adapters;

use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * VdoCipher adapter (real integration + DRM).
 *
 * Playback requires a one-time password (OTP) generated server-side with the
 * account API secret (credentials.api_secret). The signed player URL carries
 * the OTP + playbackInfo; the secret itself is never exposed.
 *
 * The OTP is single-use, so it is generated fresh on every playback request
 * (never cached/reused), otherwise reloading the player within the OTP
 * lifetime would fail to open the video.
 */
class VdoCipherVideoPlatform extends AbstractVideoPlatform
{
    private const OTP_ENDPOINT = 'https://dev.vdocipher.com/api/videos/%s/otp';

    private const PLAYER_URL = 'https://player.vdocipher.com/v2/';

    public function code(): string
    {
        return 'vdocipher';
    }

    public function playbackData(Video $video): array
    {
        $secret = $this->credential('api_secret');

        if (! $secret || ! $video->code) {
            return [
                'platform' => 'vdocipher',
                'embed_url' => $video->link,
                'video_code' => $video->code,
                'duration' => $video->time,
                'warning' => 'VdoCipher sem api_secret/video id configurado; retornando link direto.',
            ];
        }

        $response = Http::withHeaders(['Authorization' => 'Apisecret '.$secret])
            ->acceptJson()
            ->post(sprintf(self::OTP_ENDPOINT, $video->code), [
                'ttl' => (int) $this->setting('ttl', 3600),
            ]);

        if ($response->failed()) {
            Log::warning('VdoCipher OTP fetch failed', ['video_id' => $video->id, 'status' => $response->status()]);

            $data = [];
        } else {
            $data = $response->json();
        }

        $otp = $data['otp'] ?? null;
        $playbackInfo = $data['playbackInfo'] ?? null;

        return [
            'platform' => 'vdocipher',
            'embed_url' => ($otp && $playbackInfo)
                ? self::PLAYER_URL.'?otp='.urlencode($otp).'&playbackInfo='.urlencode($playbackInfo)
                : $video->link,
            'video_code' => $video->code,
            'duration' => $video->time,
            'thumbnail' => $video->image,
        ];
    }
}
