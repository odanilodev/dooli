<?php

namespace App\DTO;

class CreatePaymentDTO
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $gatewayId,
        public readonly string $method,
        public readonly float $amount,
        public readonly array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            orderId: (int) $data['order_id'],
            gatewayId: (int) $data['gateway_id'],
            method: $data['method'],
            amount: (float) $data['amount'],
            metadata: $data['metadata'] ?? [],
        );
    }
}
