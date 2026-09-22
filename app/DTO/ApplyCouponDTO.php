<?php

namespace App\DTO;

class ApplyCouponDTO
{
    public function __construct(
        public readonly string $code,
        public readonly array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            items: $data['items'] ?? [],
        );
    }
}
