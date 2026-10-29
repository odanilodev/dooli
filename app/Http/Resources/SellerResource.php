<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'code' => $this->code,
            'commission_percent' => $this->commission_percent,
            'status' => $this->status,
            'notes' => $this->notes,
            'kit_url' => $this->kitUrl(),
            'metrics' => $this->metrics ?? $this->emptyMetrics(),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyMetrics(): array
    {
        return [
            'paid_orders_count' => 0,
            'students_count' => 0,
            'signups_count' => 0,
            'gross_revenue' => 0.0,
            'commission_generated' => 0.0,
            'commission_pending' => 0.0,
            'commission_paid' => 0.0,
            'commission_refunded' => 0.0,
            'commission_count' => 0,
        ];
    }
}
