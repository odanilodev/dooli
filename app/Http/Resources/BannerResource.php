<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'placement' => $this->placement,
            'name' => $this->name,
            'path' => $this->path,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'link' => $this->link,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'title_highlight' => $this->title_highlight,
            'description' => $this->description,
            'quote' => $this->quote,
            'cta_label' => $this->cta_label,
            'cta_link' => $this->cta_link,
            'secondary_label' => $this->secondary_label,
            'secondary_link' => $this->secondary_link,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
        ];
    }
}
