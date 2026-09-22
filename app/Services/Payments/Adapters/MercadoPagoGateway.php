<?php

namespace App\Services\Payments\Adapters;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\Payments\Contracts\GatewayChargeResult;
use App\Services\Payments\Contracts\GatewayRefundResult;
use App\Services\Payments\Contracts\PaymentGatewayContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MercadoPago adapter (real integration).
 *
 * Credentials come from gateways.credentials (access_token) and are never
 * exposed through the API. When no token is configured the adapter degrades
 * to a pending local transaction so development is not blocked.
 */
class MercadoPagoGateway implements PaymentGatewayContract
{
    public function code(): string
    {
        return 'mercadopago';
    }

    public function createCharge(Payment $payment, array $options = []): GatewayChargeResult
    {
        $accessToken = $this->credential($payment, 'access_token');

        if (! $accessToken) {
            return new GatewayChargeResult(
                transactionId: 'mp-local-'.$payment->id.'-'.now()->timestamp,
                status: PaymentStatus::Pending->value,
                raw: ['warning' => 'access_token ausente; cobrança local criada.'],
            );
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://api.mercadopago.com/v1/payments', [
                'transaction_amount' => (float) $payment->amount,
                'description' => 'Pedido #'.$payment->order_id,
                'payment_method_id' => $options['payment_method_id'] ?? 'pix',
                'external_reference' => (string) $payment->id,
                'payer' => $options['payer'] ?? [],
            ]);

        if ($response->failed()) {
            Log::warning('MercadoPago charge failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
            ]);

            throw new PaymentException('Não foi possível criar a cobrança no MercadoPago.', 'gateway_charge_failed');
        }

        $data = $response->json();

        return new GatewayChargeResult(
            transactionId: (string) ($data['id'] ?? ''),
            status: $this->mapStatus($data['status'] ?? 'pending'),
            raw: $data,
        );
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): GatewayRefundResult
    {
        $accessToken = $this->credential($payment, 'access_token');

        if (! $accessToken || ! $payment->transaction_id) {
            throw new PaymentException('Gateway sem credenciais para estorno.', 'gateway_refund_unavailable');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://api.mercadopago.com/v1/payments/{$payment->transaction_id}/refunds", [
                'amount' => $amount,
            ]);

        if ($response->failed()) {
            throw new PaymentException('Falha ao solicitar estorno.', 'gateway_refund_failed');
        }

        return new GatewayRefundResult(success: true, status: 'refunded', raw: $response->json());
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');

        // Fail closed: without a configured secret no webhook can be trusted.
        if (! $secret) {
            return false;
        }

        $signature = (string) $request->header('x-signature');
        $requestId = (string) $request->header('x-request-id');

        if ($signature === '' || $requestId === '') {
            return false;
        }

        $dataId = (string) ($request->input('data.id') ?? '');

        $ts = $this->extractTs($signature);

        // Reject stale signatures to reduce replay window (5 minutes).
        if ($ts !== '' && abs((int) (microtime(true) * 1000) - (int) $ts) > 300000) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:".$ts.';';
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $this->extractV1($signature));
    }

    public function parseWebhook(Request $request): array
    {
        $type = (string) ($request->input('type') ?? $request->input('action') ?? 'payment.updated');

        return [
            'external_id' => (string) ($request->input('id') ?? $request->input('data.id') ?? ''),
            'event' => $type,
            'status' => $this->mapStatus((string) ($request->input('data.status') ?? $request->input('status') ?? '')),
            'transaction_id' => (string) ($request->input('data.id') ?? ''),
            'metadata' => $request->all(),
        ];
    }

    private function credential(Payment $payment, string $key): ?string
    {
        $gateway = $payment->gateway;

        if (! $gateway) {
            return null;
        }

        $credentials = $gateway->credentials ?? [];

        return $credentials[$key] ?? null;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'approved', 'paid' => PaymentStatus::Paid->value,
            'refunded', 'charged_back' => PaymentStatus::Refunded->value,
            'rejected', 'cancelled', 'failed' => PaymentStatus::Failed->value,
            default => PaymentStatus::Pending->value,
        };
    }

    private function extractTs(string $signature): string
    {
        if (preg_match('/ts=([^,;]+)/', $signature, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function extractV1(string $signature): string
    {
        if (preg_match('/v1=([a-f0-9]+)/i', $signature, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
