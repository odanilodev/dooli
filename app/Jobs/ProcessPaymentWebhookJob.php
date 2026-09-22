<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

    public function handle(PaymentService $payments): void
    {
        $payments->applyWebhook($this->paymentId, $this->payload);
    }
}
