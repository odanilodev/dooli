<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentException;
use App\Models\Gateway;
use App\Services\Payments\Adapters\ManualGateway;
use App\Services\Payments\Adapters\MercadoPagoGateway;
use App\Services\Payments\Contracts\PaymentGatewayContract;

/**
 * Resolves the correct gateway adapter by code (Strategy + Factory).
 * The rest of the application depends only on PaymentGatewayContract.
 */
class GatewayManager
{
    /** @var array<string, class-string<PaymentGatewayContract>> */
    protected array $adapters = [
        'mercadopago' => MercadoPagoGateway::class,
        'manual' => ManualGateway::class,
    ];

    public function for(Gateway $gateway): PaymentGatewayContract
    {
        return $this->make($gateway->code);
    }

    public function make(string $code): PaymentGatewayContract
    {
        if (! isset($this->adapters[$code])) {
            throw new PaymentException("Gateway '{$code}' não suportado.", 'gateway_unsupported');
        }

        return app($this->adapters[$code]);
    }

    public function supports(string $code): bool
    {
        return isset($this->adapters[$code]);
    }
}
