<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'company_id' => $this->company_id,
            'available_at' => $this->when(
                ($this->schedule_available_at ?? null) !== null,
                fn () => $this->schedule_available_at?->toIso8601String(),
            ),
            'is_locked' => $this->when(
                ($this->schedule_available_at ?? null) !== null,
                fn () => (bool) $this->schedule_locked,
            ),
            'modules' => ModuleResource::collection($this->whenLoaded('modules')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
