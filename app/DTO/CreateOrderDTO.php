<?php

namespace App\DTO;

class CreateOrderDTO
{
    /**
     * @param  array<int, array{store_product_id: int, quantity: int}>  $items
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $items,
        public readonly ?string $couponCode = null,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            items: $data['items'],
            couponCode: $data['coupon_code'] ?? null,
        );
    }
}
