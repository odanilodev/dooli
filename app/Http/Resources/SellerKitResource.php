<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Seller;
use App\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class SellerKitResource extends JsonResource
{
    /**
     * @param  Collection<int, StoreProduct>  $products
     */
    public function __construct(
        Seller $seller,
        private readonly Collection $products,
        private readonly Company $company,
    ) {
        parent::__construct($seller);
    }

    public function toArray(Request $request): array
    {
        return [
            'seller' => [
                'name' => $this->name,
                'code' => $this->code,
            ],
            'store_url' => $this->company->publicStoreUrl($this->code),
            'products' => $this->products->map(fn (StoreProduct $product) => [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'image_url' => $product->imageUrl(),
                'price' => $product->price,
                'sale' => $product->sale,
                'is_subscription' => $product->isSubscription(),
                'access_label' => $product->accessLabel(),
                'share_url' => $this->company->publicProductUrl($product->slug, $this->code),
            ])->values(),
        ];
    }
}
