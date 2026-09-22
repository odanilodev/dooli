<?php

namespace App\Services\Payments\Adapters;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\Contracts\GatewayChargeResult;
use App\Services\Payments\Contracts\GatewayRefundResult;
use App\Services\Payments\Contracts\PaymentGatewayContract;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manual/offline gateway. Used for bank transfer or manual confirmation.
 */
class ManualGateway implements PaymentGatewayContract
{
    public function code(): string
    {
        return 'manual';
    }

    public function createCharge(Payment $payment, array $options = []): GatewayChargeResult
    {
        return new GatewayChargeResult(
            transactionId: 'manual-'.Str::uuid(),
            status: PaymentStatus::Pending->value,
        );
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): GatewayRefundResult
    {
        return new GatewayRefundResult(success: true, status: 'refunded');
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = config('services.manual.webhook_secret');

        // Fail closed: without a configured secret no webhook can be trusted.
        if (! $secret) {
            return false;
        }

        return hash_equals($secret, (string) $request->header('X-Webhook-Secret'));
    }

    public function parseWebhook(Request $request): array
    {
        return [
            'external_id' => $request->input('event_id'),
            'event' => $request->input('event', 'payment.updated'),
            'status' => $request->input('status', PaymentStatus::Paid->value),
            'transaction_id' => $request->input('transaction_id'),
            'metadata' => $request->all(),
        ];
    }
}
