<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'sub' => $this->sub === null ? null : (string) $this->sub,
            'link' => $this->link,
            'sort_order' => $this->sort_order,
            'icon' => $this->icon,
        ];
    }
}
