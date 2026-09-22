<?php

namespace App\Services\Payments\Contracts;

class GatewayChargeResult
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $status,
        public readonly array $raw = [],
    ) {}
}
