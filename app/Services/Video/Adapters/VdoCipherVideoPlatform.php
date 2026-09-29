<?php

namespace App\Services\Video\Adapters;

use App\Exceptions\BusinessRuleException;
use App\Models\Video;
use Illuminate\Http\Client\PendingRequest;
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
 *
 * The same secret also drives the upload API, letting the admin register and
 * upload videos without opening the VdoCipher dashboard.
 */
class VdoCipherVideoPlatform extends AbstractVideoPlatform
{
    private const API_BASE = 'https://dev.vdocipher.com/api/videos';

    private const OTP_ENDPOINT = self::API_BASE.'/%s/otp';

    private const VIDEO_ENDPOINT = self::API_BASE.'/%s';

    private const IMPORT_URL_ENDPOINT = self::API_BASE.'/importUrl';

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

        $response = $this->client()
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

    public function supportsUpload(): bool
    {
        return true;
    }

    public function importFromUrl(string $url, string $title, ?string $folderId = null): array
    {
        $this->assertConfigured();

        $response = $this->client()
            ->put(self::IMPORT_URL_ENDPOINT, array_filter([
                'url' => $url,
                'title' => $title,
                'folderId' => $this->folderId($folderId),
            ]));

        if ($response->failed()) {
            Log::warning('VdoCipher importUrl failed', ['status' => $response->status()]);

            throw new BusinessRuleException('Não foi possível importar o vídeo no VdoCipher.');
        }

        $data = $response->json();

        return [
            'video_code' => (string) ($data['videoId'] ?? $data['id'] ?? ''),
            'status' => $data['status'] ?? 'Queued',
        ];
    }

    public function createUploadCredentials(string $title, ?string $folderId = null): array
    {
        $this->assertConfigured();

        $response = $this->client()
            ->put(self::API_BASE, array_filter([
                'title' => $title,
                'folderId' => $this->folderId($folderId),
            ]));

        if ($response->failed()) {
            Log::warning('VdoCipher upload credentials failed', ['status' => $response->status()]);

            throw new BusinessRuleException('Não foi possível iniciar o upload no VdoCipher.');
        }

        $data = $response->json();

        return [
            'video_code' => (string) ($data['videoId'] ?? ''),
            'upload' => is_array($data['clientPayload'] ?? null) ? $data['clientPayload'] : [],
        ];
    }

    public function status(string $videoId): array
    {
        $this->assertConfigured();

        $response = $this->client()->get(sprintf(self::VIDEO_ENDPOINT, $videoId));

        if ($response->failed()) {
            Log::warning('VdoCipher status fetch failed', ['video_id' => $videoId, 'status' => $response->status()]);

            throw new BusinessRuleException('Não foi possível consultar o status no VdoCipher.');
        }

        $data = $response->json();

        return [
            'status' => $data['status'] ?? null,
            'duration' => isset($data['length']) ? (int) $data['length'] : null,
            'thumbnail' => $data['posters'][0]['posterUrl'] ?? null,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Apisecret '.$this->credential('api_secret')])
            ->acceptJson();
    }

    private function assertConfigured(): void
    {
        if (! $this->credential('api_secret')) {
            throw new BusinessRuleException('Configure o api_secret do VdoCipher em Plataformas de vídeo.');
        }
    }

    private function folderId(?string $folderId): ?string
    {
        return $folderId ?: $this->setting('folder_id');
    }
}
