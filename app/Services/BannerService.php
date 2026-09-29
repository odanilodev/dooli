<?php

namespace App\Services;

use App\Models\Banner;
use App\Repositories\BannerRepository;
use App\Support\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BannerService
{
    public function __construct(
        private readonly BannerRepository $banners,
        private readonly CompanyContext $context,
        private readonly DatalogService $audit,
    ) {}

    public function create(array $data, ?UploadedFile $image = null): Banner
    {
        $companyId = (int) ($data['company_id'] ?? $this->context->id() ?? 0);

        if ($image) {
            $data['image'] = $this->storeImage($image, $companyId);
        }

        $banner = $this->banners->create($data);

        $this->audit->log('banner', 'create', 'banner', "Banner criado: {$banner->name}");

        return $banner;
    }

    public function update(Banner $banner, array $data, ?UploadedFile $image = null): Banner
    {
        if ($image) {
            $this->deleteImage($banner->image);
            $data['image'] = $this->storeImage($image, (int) $banner->company_id);
        }

        $banner = $this->banners->update($banner, $data);

        $this->audit->log('banner', 'update', 'banner', "Banner atualizado: {$banner->name}");

        return $banner;
    }

    public function delete(Banner $banner): void
    {
        $this->deleteImage($banner->image);

        $this->banners->delete($banner);

        $this->audit->log('banner', 'delete', 'banner', "Banner excluído: {$banner->name}");
    }

    private function storeImage(UploadedFile $image, int $companyId): string
    {
        return $image->store("banners/{$companyId}", 'public');
    }

    private function deleteImage(?string $image): void
    {
        if (empty($image) || str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($image);
    }
}
