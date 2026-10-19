<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ResourceNotFoundException;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Gateway;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Services\Payments\GatewayManager;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

class WebhookService
{
    public function __construct(
        private readonly GatewayManager $gatewayManager,
        private readonly PaymentRepository $payments,
        private readonly DatalogService $audit,
    ) {}

    /**
     * Verifies, resolves and queues a gateway webhook for processing.
     *
     * @return array{status: string, payment_id: int}
     */
    public function handle(Gateway $gateway, Request $request): array
    {
        $adapter = $this->gatewayManager->for($gateway);

        if (! $adapter->verifyWebhook($request, $gateway)) {
            throw new ApiException('Assinatura do webhook inválida.', 401, 'invalid_webhook_signature');
        }

        $payload = $adapter->parseWebhook($request);

        if (method_exists($adapter, 'enrichWebhook')) {
            $payload = $adapter->enrichWebhook($payload, $gateway);
        }

        $payment = $this->resolvePayment($payload);

        if (! $payment) {
            throw new ResourceNotFoundException('Pagamento não encontrado para o webhook.');
        }

        // The tenant is derived from the resolved payment, never from the
        // (unauthenticated, client-controlled) request headers.
        if ((int) $payment->company_id !== (int) $gateway->company_id) {
            throw new ResourceNotFoundException('Pagamento não encontrado para o webhook.');
        }

        app(CompanyContext::class)->set((int) $payment->company_id);

        ProcessPaymentWebhookJob::dispatch($payment->id, $payload);

        $this->audit->log('webhook', 'receive', 'payment', "Webhook {$payload['event']} recebido para pagamento {$payment->id}");

        return ['status' => 'queued', 'payment_id' => $payment->id];
    }

    /**
     * @param  array{transaction_id: ?string, metadata: array}  $payload
     */
    private function resolvePayment(array $payload): ?Payment
    {
        if (! empty($payload['transaction_id'])) {
            $payment = $this->payments->findByTransactionIdAcrossCompanies($payload['transaction_id']);

            if ($payment) {
                return $payment;
            }
        }

        $localPaymentId = $payload['metadata']['local_payment_id'] ?? null;

        if ($localPaymentId) {
            return $this->payments->findAcrossCompanies((int) $localPaymentId);
        }

        $reference = $payload['metadata']['external_reference'] ?? $payload['metadata']['data']['external_reference'] ?? null;

        if ($reference) {
            return $this->payments->findAcrossCompanies((int) $reference);
        }

        return null;
    }
}
