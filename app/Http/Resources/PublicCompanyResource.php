<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'public_store_url' => $this->publicStoreUrl(),
            'branding' => $this->branding ?? null,
            'storefront' => ($this->storefront ?? null) ? [
                'settings' => $this->storefront['settings'] ?? null,
                'banners' => BannerResource::collection($this->storefront['banners'] ?? []),
            ] : null,
            'gateways' => $this->whenLoaded('gateways', fn () => $this->gateways->map(fn ($gateway) => [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'code' => $gateway->code,
                // A Public Key do Mercado Pago é pública por natureza (usada no navegador para tokenizar o cartão).
                'public_key' => $gateway->code === 'mercadopago'
                    ? ($gateway->credentials['public_key'] ?? null)
                    : null,
            ])),
        ];
    }
}
