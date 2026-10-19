<?php

namespace App\Services\Payments\Contracts;

use App\Models\Gateway;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    public function code(): string;

    /**
     * Creates a charge at the gateway for the given payment.
     */
    public function createCharge(Payment $payment, array $options = []): GatewayChargeResult;

    public function refund(Payment $payment, float $amount, ?string $reason = null): GatewayRefundResult;

    /**
     * Validates the authenticity of an incoming webhook (signature/secret).
     */
    public function verifyWebhook(Request $request, Gateway $gateway): bool;

    /**
     * Normalizes the webhook payload.
     *
     * @return array{external_id: ?string, event: string, status: string, transaction_id: ?string, metadata: array}
     */
    public function parseWebhook(Request $request): array;
}
