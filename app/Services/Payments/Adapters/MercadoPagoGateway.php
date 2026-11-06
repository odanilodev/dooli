<?php

namespace App\Services\Payments\Adapters;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Gateway;
use App\Models\Payment;
use App\Models\Subscription;
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

        // O Mercado Pago exige o e-mail do pagador (PIX) e o header X-Idempotency-Key.
        $payment->loadMissing('order.user');
        $user = $payment->order?->user;
        $payer = $options['payer'] ?? [];

        if (empty($payer['email']) && $user?->email) {
            $payer['email'] = $user->email;
        }

        if (empty($payer['first_name']) && $user?->name) {
            $payer['first_name'] = $user->name;
        }

        $body = [
            'transaction_amount' => (float) $payment->amount,
            'description' => 'Pedido #'.$payment->order_id,
            'payment_method_id' => $options['payment_method_id'] ?? 'pix',
            'external_reference' => (string) $payment->id,
            'payer' => $payer,
        ];

        // Cartão (checkout transparente): token gerado no navegador pelo MP.js.
        if (! empty($options['token'])) {
            $body['token'] = $options['token'];
            $body['installments'] = max(1, (int) ($options['installments'] ?? 1));

            if (! empty($options['issuer_id'])) {
                $body['issuer_id'] = (int) $options['issuer_id'];
            }
        }

        $headers = ['X-Idempotency-Key' => 'payment-'.$payment->id];

        if (! empty($options['device_id'])) {
            $headers['X-meli-session-id'] = $options['device_id'];
        }

        $response = Http::withToken($accessToken)
            ->withHeaders($headers)
            ->acceptJson()
            ->post('https://api.mercadopago.com/v1/payments', $body);

        if ($response->failed()) {
            Log::warning('MercadoPago charge failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
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

    public function createPreference(Payment $payment, Gateway $gateway): array
    {
        $accessToken = $gateway->credentials['access_token'] ?? null;

        if (! $accessToken) {
            return [
                'id' => 'mp-local-pref-'.$payment->id,
                'init_point' => null,
                'raw' => ['warning' => 'access_token ausente; checkout local criado.'],
            ];
        }

        $payment->loadMissing(['order.items.storeProduct']);
        $company = $gateway->company;
        $slug = (string) $company?->slug;
        $api = rtrim((string) config('app.url'), '/');
        $order = $payment->order;
        $orderUrl = $company?->publicOrderUrl((int) $order->id)
            ?? rtrim((string) config('app.frontend_url'), '/').'/#/e/'.$slug.'/pedido/'.$order->id;

        $items = [[
            'title' => $order->items->map(fn ($item) => $item->storeProduct?->title)->filter()->implode(', ') ?: 'Pedido',
            'quantity' => 1,
            'unit_price' => (float) $order->total,
            'currency_id' => 'BRL',
        ]];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://api.mercadopago.com/checkout/preferences', [
                'items' => $items,
                'external_reference' => (string) $payment->id,
                'notification_url' => $api.'/api/v1/webhooks/companies/'.$slug.'/payments/mercadopago?local_payment_id='.$payment->id,
                'back_urls' => [
                    'success' => $orderUrl,
                    'failure' => $orderUrl,
                    'pending' => $orderUrl,
                ],
                'auto_return' => 'approved',
            ]);

        if ($response->failed()) {
            Log::warning('MercadoPago preference failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
            ]);

            throw new PaymentException('Não foi possível criar o checkout de cartão.', 'gateway_preference_failed');
        }

        $data = $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'init_point' => $data['init_point'] ?? $data['sandbox_init_point'] ?? null,
            'raw' => $data,
        ];
    }

    public function createSubscription(Subscription $subscription, Gateway $gateway): array
    {
        $accessToken = $gateway->credentials['access_token'] ?? null;

        if (! $accessToken) {
            return [
                'id' => 'mp-local-sub-'.$subscription->id,
                'init_point' => null,
                'raw' => ['warning' => 'access_token ausente; assinatura local criada.'],
            ];
        }

        $subscription->loadMissing(['user', 'storeProduct']);
        $company = $gateway->company;
        $slug = (string) $company?->slug;
        $subscriptionsUrl = $company?->publicSubscriptionsUrl()
            ?? rtrim((string) config('app.frontend_url'), '/').'/#/e/'.$slug.'/assinaturas';

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://api.mercadopago.com/preapproval', [
                'reason' => 'Assinatura: '.$subscription->storeProduct?->title,
                'external_reference' => (string) $subscription->id,
                'payer_email' => $subscription->user?->email,
                'back_url' => $subscriptionsUrl,
                'notification_url' => rtrim((string) config('app.url'), '/').'/api/v1/webhooks/companies/'.$slug.'/subscriptions/mercadopago',
                'status' => 'pending',
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => 'months',
                    'transaction_amount' => (float) $subscription->amount,
                    'currency_id' => 'BRL',
                ],
            ]);

        if ($response->failed()) {
            Log::warning('MercadoPago preapproval failed', [
                'subscription_id' => $subscription->id,
                'status' => $response->status(),
            ]);

            throw new PaymentException('Não foi possível criar a assinatura no MercadoPago.', 'gateway_subscription_failed');
        }

        $data = $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'init_point' => $data['init_point'] ?? null,
            'raw' => $data,
        ];
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

    public function verifyWebhook(Request $request, Gateway $gateway): bool
    {
        $secret = $gateway->credentials['webhook_secret'] ?? config('services.mercadopago.webhook_secret');

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

    /**
     * Fetch the authoritative resource from Mercado Pago for payment webhooks.
     * The webhook body often only contains the resource id.
     */
    public function enrichWebhook(array $payload, Gateway $gateway): array
    {
        $accessToken = $gateway->credentials['access_token'] ?? null;
        $id = $payload['transaction_id'] ?? $payload['external_id'] ?? null;

        if (! $accessToken || ! $id) {
            return $payload;
        }

        $type = (string) ($payload['event'] ?? '');

        if (! str_contains($type, 'payment')) {
            return $payload;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://api.mercadopago.com/v1/payments/{$id}");

        if ($response->failed()) {
            Log::warning('MercadoPago webhook payment lookup failed', ['id' => $id, 'status' => $response->status()]);

            return $payload;
        }

        $data = $response->json();

        return array_merge($payload, [
            'status' => $this->mapStatus((string) ($data['status'] ?? '')),
            'transaction_id' => (string) ($data['id'] ?? $id),
            'paid_at' => $data['date_approved'] ?? null,
            'metadata' => array_merge($payload['metadata'] ?? [], $data),
        ]);
    }

    public function resolveSubscriptionWebhook(array $payload, Gateway $gateway): array
    {
        $accessToken = $gateway->credentials['access_token'] ?? null;
        $id = $payload['data']['id'] ?? $payload['id'] ?? null;
        $type = (string) ($payload['type'] ?? $payload['action'] ?? '');

        if (! $accessToken || ! $id) {
            return $payload;
        }

        if (str_contains($type, 'authorized_payment') || str_contains($type, 'payment')) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get("https://api.mercadopago.com/v1/payments/{$id}");

            if ($response->failed()) {
                return $payload;
            }

            $data = $response->json();

            return [
                'provider_subscription_id' => $data['metadata']['preapproval_id']
                    ?? $data['preapproval_id']
                    ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'status' => $this->mapStatus((string) ($data['status'] ?? '')),
                'transaction_id' => (string) ($data['id'] ?? $id),
                'paid_at' => $data['date_approved'] ?? null,
            ];
        }

        if (str_contains($type, 'preapproval')) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get("https://api.mercadopago.com/preapproval/{$id}");

            if ($response->failed()) {
                return $payload;
            }

            $data = $response->json();

            return [
                'provider_subscription_id' => (string) ($data['id'] ?? $id),
                'external_reference' => $data['external_reference'] ?? null,
                'status' => match ((string) ($data['status'] ?? '')) {
                    'authorized' => 'paid',
                    'cancelled', 'canceled' => 'canceled',
                    'paused' => 'past_due',
                    default => 'pending',
                },
                'transaction_id' => null,
                'paid_at' => null,
            ];
        }

        return $payload;
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
