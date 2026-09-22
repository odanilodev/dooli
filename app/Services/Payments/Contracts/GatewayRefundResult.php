<?php

namespace App\Services\Payments\Contracts;

class GatewayRefundResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly array $raw = [],
    ) {}
}
