<?php

namespace App\Services;

use App\Repositories\CompanySettingRepository;
use App\Support\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanySettingService
{
    private const LOGO_KEY = 'logo';

    private const COLOR_KEY = 'primary_color';

    public function __construct(
        private readonly CompanySettingRepository $settings,
        private readonly ImageStorage $images,
    ) {}

    /**
     * Branding values exposed to the frontend.
     *
     * @return array{primary_color: string|null, logo: string|null, logo_url: string|null}
     */
    public function branding(int $companyId): array
    {
        $logo = $this->settings->get($companyId, self::LOGO_KEY);

        return [
            'primary_color' => $this->settings->get($companyId, self::COLOR_KEY),
            'logo' => $logo,
            'logo_url' => $logo ? Storage::disk('public')->url($logo) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{primary_color: string|null, logo: string|null, logo_url: string|null}
     */
    public function update(int $companyId, array $data, ?UploadedFile $logo = null): array
    {
        if (array_key_exists('primary_color', $data)) {
            $this->settings->put($companyId, self::COLOR_KEY, $data['primary_color']);
        }

        if ($logo) {
            $this->images->delete($this->settings->get($companyId, self::LOGO_KEY));
            $path = $this->images->store($logo, 'branding', $companyId);
            $this->settings->put($companyId, self::LOGO_KEY, $path);
        }

        return $this->branding($companyId);
    }
}
