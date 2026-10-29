<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerCommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'code' => $this->seller->code,
            ]),
            'order_id' => $this->order_id,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'status' => $this->order->status,
                'total' => $this->order->total,
                'user' => $this->order->relationLoaded('user') && $this->order->user
                    ? [
                        'id' => $this->order->user->id,
                        'name' => $this->order->user->name,
                        'email' => $this->order->user->email,
                    ]
                    : null,
            ]),
            'order_item_id' => $this->order_item_id,
            'product' => $this->whenLoaded('orderItem', fn () => [
                'id' => $this->orderItem->store_product_id,
                'title' => $this->orderItem->product_title,
                'quantity' => $this->orderItem->quantity,
            ]),
            'base_amount' => $this->base_amount,
            'commission_percent' => $this->commission_percent,
            'amount' => $this->amount,
            'refunded_amount' => $this->refunded_amount,
            'net_amount' => $this->netAmount(),
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'payment_reference' => $this->payment_reference,
            'payment_notes' => $this->payment_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
