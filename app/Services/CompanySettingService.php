<?php

namespace App\Services;

use App\Repositories\CompanySettingRepository;
use App\Support\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanySettingService
{
    private const LOGO_LEGACY_KEY = 'logo';

    private const LOGO_LIGHT_KEY = 'logo_light';

    private const LOGO_DARK_KEY = 'logo_dark';

    private const COLOR_KEY = 'primary_color';

    public function __construct(
        private readonly CompanySettingRepository $settings,
        private readonly ImageStorage $images,
    ) {}

    /**
     * Branding values exposed to the frontend.
     *
     * @return array{
     *     primary_color: string|null,
     *     logo_light: string|null,
     *     logo_light_url: string|null,
     *     logo_dark: string|null,
     *     logo_dark_url: string|null,
     *     logo: string|null,
     *     logo_url: string|null
     * }
     */
    public function branding(int $companyId): array
    {
        $legacyLogo = $this->settings->get($companyId, self::LOGO_LEGACY_KEY);
        $lightLogo = $this->settings->get($companyId, self::LOGO_LIGHT_KEY) ?: $legacyLogo;
        $darkLogo = $this->settings->get($companyId, self::LOGO_DARK_KEY);
        $lightLogoUrl = $this->publicUrl($lightLogo);

        return [
            'primary_color' => $this->settings->get($companyId, self::COLOR_KEY),
            'logo_light' => $lightLogo,
            'logo_light_url' => $lightLogoUrl,
            'logo_dark' => $darkLogo,
            'logo_dark_url' => $this->publicUrl($darkLogo),
            'logo' => $lightLogo,
            'logo_url' => $lightLogoUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     primary_color: string|null,
     *     logo_light: string|null,
     *     logo_light_url: string|null,
     *     logo_dark: string|null,
     *     logo_dark_url: string|null,
     *     logo: string|null,
     *     logo_url: string|null
     * }
     */
    public function update(
        int $companyId,
        array $data,
        ?UploadedFile $lightLogo = null,
        ?UploadedFile $darkLogo = null,
        ?UploadedFile $legacyLogo = null,
    ): array {
        if (array_key_exists('primary_color', $data)) {
            $this->settings->put($companyId, self::COLOR_KEY, $data['primary_color']);
        }

        $lightLogo ??= $legacyLogo;

        if ($lightLogo) {
            $this->replaceLogo($companyId, self::LOGO_LIGHT_KEY, $lightLogo);
        }

        if ($darkLogo) {
            $this->replaceLogo($companyId, self::LOGO_DARK_KEY, $darkLogo);
        }

        return $this->branding($companyId);
    }

    private function replaceLogo(int $companyId, string $key, UploadedFile $logo): void
    {
        $this->images->delete($this->settings->get($companyId, $key));

        $this->settings->put(
            $companyId,
            $key,
            $this->images->store($logo, 'branding', $companyId),
        );
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
