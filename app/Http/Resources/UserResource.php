<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status,
            'is_super_admin' => $this->isSuperAdmin(),
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'company_id' => $this->company_id,
            'user_type' => new UserTypeResource($this->whenLoaded('userType')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
