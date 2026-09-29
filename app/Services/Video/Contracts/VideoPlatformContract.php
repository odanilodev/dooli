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

    /**
     * Whether the platform supports uploading/importing videos through the API.
     */
    public function supportsUpload(): bool;

    /**
     * Imports a video from a remote URL, returning its provider code and status.
     *
     * @return array{video_code: string, status: ?string}
     */
    public function importFromUrl(string $url, string $title, ?string $folderId = null): array;

    /**
     * Requests temporary (upload-only) credentials so the client uploads the
     * file straight to the provider. Never exposes the API secret.
     *
     * @return array{video_code: string, upload: array<string, mixed>}
     */
    public function createUploadCredentials(string $title, ?string $folderId = null): array;

    /**
     * Fetches the provider status/metadata of a video (status, duration, poster).
     *
     * @return array{status: ?string, duration: ?int, thumbnail: ?string}
     */
    public function status(string $videoId): array;
}
