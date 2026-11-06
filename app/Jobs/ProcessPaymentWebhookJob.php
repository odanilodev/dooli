<?php

namespace App\Jobs;

use App\Repositories\PaymentRepository;
use App\Services\DatalogService;
use App\Services\PaymentService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{external_id: ?string, event: string, status: string, transaction_id: ?string, metadata: array}  $payload
     */
    public function __construct(
        public int $paymentId,
        public array $payload,
    ) {}

    public function handle(PaymentService $payments, DatalogService $audit): void
    {
        $result = $payments->applyWebhook($this->paymentId, $this->payload);

        // applyWebhook define o contexto da empresa do pagamento.
        $audit->log(
            'webhook',
            'processed',
            'payment',
            "Webhook do pagamento {$this->paymentId} processado (resultado: {$result}).",
        );
    }

    public function failed(Throwable $exception): void
    {
        try {
            $payment = app(PaymentRepository::class)->findAcrossCompanies($this->paymentId);

            if ($payment) {
                app(CompanyContext::class)->set((int) $payment->company_id);

                app(DatalogService::class)->log(
                    'webhook',
                    'failed',
                    'payment',
                    "Falha ao processar o webhook do pagamento {$this->paymentId}: ".mb_substr($exception->getMessage(), 0, 300),
                );
            }
        } catch (Throwable) {
            // Falha ao auditar não deve mascarar a falha original.
        }
    }
}
