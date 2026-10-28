<?php

namespace App\Services;

use App\DTO\CreatePaymentDTO;
use App\Enums\OrderEventActorType;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentEventStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\PaymentException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Repositories\GatewayRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly OrderRepository $orders,
        private readonly GatewayRepository $gateways,
        private readonly Payments\GatewayManager $gatewayManager,
        private readonly EnrollmentService $enrollments,
        private readonly DatalogService $audit,
        private readonly OrderEventService $orderEvents,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->payments->paginate(
            perPage: $perPage,
            filters: $filters,
            with: ['gateway', 'order.user'],
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int|array<string, int>>
     */
    public function summary(array $filters): array
    {
        return $this->payments->summary($filters);
    }

    public function find(int $id): Payment
    {
        return $this->payments->findWithEvents($id);
    }

    public function create(CreatePaymentDTO $dto): Payment
    {
        $order = $this->orders->findWithRelations($dto->orderId);

        if ($order->status === OrderStatus::Paid->value) {
            throw new PaymentException('Pedido já está pago.', 'order_already_paid');
        }

        if ($order->status === OrderStatus::Canceled->value) {
            throw new PaymentException('Pedido cancelado.', 'order_canceled');
        }

        $gateway = $this->gateways->findOrFail($dto->gatewayId);

        return DB::transaction(function () use ($order, $gateway, $dto) {
            $payment = $this->payments->create([
                'order_id' => $order->id,
                'gateway_id' => $gateway->id,
                'method' => $dto->method,
                'status' => PaymentStatus::Pending->value,
                'amount' => $dto->amount,
            ]);

            $result = $this->gatewayManager->for($gateway)->createCharge($payment, $dto->metadata);

            $payment = $this->payments->update($payment, [
                'transaction_id' => $result->transactionId,
                'status' => $result->status,
                'metadata' => $result->raw ?: null,
            ]);

            PaymentEvent::query()->create([
                'payment_id' => $payment->id,
                'status' => PaymentEventStatus::Received->value,
                'event' => 'charge.created',
                'description' => 'Cobrança criada no gateway.',
                'metadata' => $result->raw,
            ]);

            $this->orderEvents->record($order, OrderEventType::PaymentStarted, [
                'payment_id' => $payment->id,
                'status' => PaymentStatus::Pending->value,
                'description' => $dto->method === 'pix'
                    ? 'Aguardando a confirmação do PIX.'
                    : 'Aguardando a confirmação do pagamento.',
                'source_type' => 'payment',
                'source_id' => (int) $payment->id,
                'occurred_at' => $payment->created_at,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'method' => $dto->method,
                    'amount' => (float) $payment->amount,
                    'gateway' => $gateway->name,
                ],
            ]);

            $this->audit->log('payment', 'create', 'payment', "Pagamento criado: {$payment->id}");

            if ($result->status === PaymentStatus::Paid->value) {
                $this->confirm($payment, ['source' => 'gateway_charge']);
            }

            return $payment->refresh()->load(['gateway', 'order', 'events']);
        });
    }

    /**
     * Creates a hosted card checkout (MercadoPago Checkout Pro) for an order.
     *
     * @return array{payment: Payment, checkout_url: string|null}
     */
    public function createCardPreference(Order $order, Gateway $gateway): array
    {
        if ($order->status === OrderStatus::Paid->value) {
            throw new PaymentException('Pedido já está pago.', 'order_already_paid');
        }

        if ($order->status === OrderStatus::Canceled->value) {
            throw new PaymentException('Pedido cancelado.', 'order_canceled');
        }

        $adapter = $this->gatewayManager->for($gateway);

        if (! method_exists($adapter, 'createPreference')) {
            throw new PaymentException('Este gateway não suporta checkout de cartão.', 'gateway_card_unsupported');
        }

        return DB::transaction(function () use ($order, $gateway, $adapter) {
            $payment = $this->payments->create([
                'order_id' => $order->id,
                'gateway_id' => $gateway->id,
                'method' => 'credit_card',
                'status' => PaymentStatus::Pending->value,
                'amount' => $order->total,
            ]);

            $result = $adapter->createPreference($payment, $gateway);

            $payment = $this->payments->update($payment, [
                'metadata' => [
                    'checkout_url' => $result['init_point'] ?? null,
                    'preference_id' => $result['id'] ?? null,
                    'gateway' => $result['raw'] ?? [],
                ],
            ]);

            PaymentEvent::query()->create([
                'payment_id' => $payment->id,
                'status' => PaymentEventStatus::Received->value,
                'event' => 'preference.created',
                'description' => 'Checkout de cartão criado no gateway.',
                'metadata' => $result['raw'] ?? [],
            ]);

            $this->orderEvents->record($order, OrderEventType::PaymentStarted, [
                'payment_id' => $payment->id,
                'status' => PaymentStatus::Pending->value,
                'description' => 'Checkout de cartão criado. Aguardando a confirmação do gateway.',
                'source_type' => 'payment',
                'source_id' => (int) $payment->id,
                'occurred_at' => $payment->created_at,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'method' => 'credit_card',
                    'amount' => (float) $payment->amount,
                    'gateway' => $gateway->name,
                ],
            ]);

            $this->audit->log('payment', 'create', 'payment', "Checkout de cartão criado: {$payment->id}");

            return [
                'payment' => $payment->refresh()->load(['gateway', 'order', 'events']),
                'checkout_url' => $result['init_point'] ?? null,
            ];
        });
    }

    /**
     * Confirms a payment and releases course access. Idempotent.
     */
    public function confirm(Payment $payment, array $context = []): Payment
    {
        if ($payment->status === PaymentStatus::Paid->value) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $context) {
            $order = $payment->order()->firstOrFail();

            if ($order->status === OrderStatus::Canceled->value) {
                $this->orderEvents->record($order, OrderEventType::PaymentBlocked, [
                    'payment_id' => $payment->id,
                    'status' => OrderStatus::Canceled->value,
                    'description' => 'A confirmação foi ignorada porque o pedido já estava cancelado.',
                    'actor_type' => OrderEventActorType::Gateway->value,
                    'actor_name' => $payment->gateway?->name,
                    'source_type' => 'payment',
                    'source_id' => (int) $payment->id,
                    'metadata' => ['source' => $context['source'] ?? null],
                ]);

                return $payment;
            }

            $paidAt = ! empty($context['paid_at'])
                ? Carbon::parse($context['paid_at'])
                : ($payment->paid_at ? Carbon::parse($payment->paid_at) : now());

            $payment = $this->payments->update($payment, [
                'status' => PaymentStatus::Paid->value,
                'paid_at' => $paidAt,
            ]);

            $order->update(['status' => OrderStatus::Paid->value]);

            if ($order->subscription_id) {
                $order->loadMissing('subscription.storeProduct.teams');

                if ($order->subscription) {
                    $periodStart = $order->period_start
                        ? Carbon::parse($order->period_start)
                        : $paidAt->copy();
                    $periodEnd = $order->period_end
                        ? Carbon::parse($order->period_end)
                        : $periodStart->copy()->addMonthNoOverflow();

                    $this->enrollments->grantAccessForSubscription(
                        $order->subscription,
                        $periodStart,
                        $periodEnd,
                    );
                }
            } else {
                $this->enrollments->grantAccessForOrder(
                    $order->load('items.storeProduct'),
                    $paidAt,
                );
            }

            PaymentEvent::query()->create([
                'payment_id' => $payment->id,
                'status' => PaymentEventStatus::Processed->value,
                'event' => 'payment.paid',
                'description' => 'Pagamento confirmado e acesso liberado.',
                'metadata' => [...$context, 'paid_at' => $paidAt->toIso8601String()],
            ]);

            $this->orderEvents->record($order, OrderEventType::PaymentApproved, [
                'payment_id' => $payment->id,
                'status' => PaymentStatus::Paid->value,
                'description' => 'O pagamento foi confirmado pelo gateway.',
                'actor_type' => OrderEventActorType::Gateway->value,
                'actor_name' => $payment->gateway?->name,
                'source_type' => 'payment',
                'source_id' => (int) $payment->id,
                'occurred_at' => $paidAt,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                    'transaction_id' => $payment->transaction_id,
                    'gateway' => $payment->gateway?->name,
                    'source' => $context['source'] ?? null,
                ],
            ]);

            $this->orderEvents->record($order, OrderEventType::AccessGranted, [
                'payment_id' => $payment->id,
                'status' => OrderStatus::Paid->value,
                'description' => 'O conteúdo da compra foi disponibilizado na área do aluno.',
                'actor_type' => OrderEventActorType::System->value,
                'source_type' => 'order',
                'source_id' => (int) $order->id,
                'occurred_at' => $paidAt,
                'metadata' => ['payment_id' => $payment->id],
            ]);

            $this->audit->log('payment', 'confirm', 'payment', "Pagamento confirmado: {$payment->id}");

            return $payment->refresh();
        });
    }

    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): Payment
    {
        if ($payment->status !== PaymentStatus::Paid->value) {
            throw new PaymentException('Somente pagamentos confirmados podem ser estornados.', 'payment_not_paid');
        }

        if (! $payment->gateway) {
            throw new PaymentException('O gateway deste pagamento não está mais disponível.', 'gateway_unavailable');
        }

        $alreadyRefunded = (float) $payment->refunds()->sum('amount');
        $refundable = round((float) $payment->amount - $alreadyRefunded, 2);

        if ($refundable <= 0) {
            throw new PaymentException('Este pagamento já foi totalmente estornado.', 'payment_already_refunded');
        }

        $amount = $amount === null ? $refundable : round($amount, 2);

        if ($amount > $refundable) {
            throw new PaymentException('O valor do estorno excede o saldo disponível.', 'refund_amount_exceeded');
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $refundable) {
            $result = $this->gatewayManager->for($payment->gateway)->refund($payment, $amount, $reason);
            $order = $payment->order()->firstOrFail();
            $refundedAt = ! empty($result->raw['date_created'])
                ? Carbon::parse($result->raw['date_created'])
                : now();

            $refund = $payment->refunds()->create([
                'amount' => $amount,
                'reason' => $reason,
                'status' => $result->status,
                'refunded_at' => $refundedAt,
            ]);

            $fullyRefunded = $amount >= $refundable;

            if ($fullyRefunded) {
                $payment = $this->payments->update($payment, ['status' => PaymentStatus::Refunded->value]);
                $order->update(['status' => OrderStatus::Refunded->value]);
            }

            PaymentEvent::query()->create([
                'payment_id' => $payment->id,
                'status' => PaymentEventStatus::Processed->value,
                'event' => 'payment.refunded',
                'description' => $reason ?? 'Estorno solicitado.',
                'metadata' => ['amount' => $amount, 'fully_refunded' => $fullyRefunded],
            ]);

            $this->orderEvents->record($order, OrderEventType::PaymentRefunded, [
                'payment_id' => $payment->id,
                'status' => PaymentStatus::Refunded->value,
                'description' => $reason ?? 'O valor foi devolvido ao comprador.',
                'source_type' => 'refund',
                'source_id' => (int) $refund->id,
                'occurred_at' => $refundedAt,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'fully_refunded' => $fullyRefunded,
                ],
            ]);

            if ($fullyRefunded) {
                $this->orderEvents->record($order, OrderEventType::OrderRefunded, [
                    'status' => OrderStatus::Refunded->value,
                    'description' => 'O valor da compra foi devolvido integralmente.',
                    'source_type' => 'order',
                    'source_id' => (int) $order->id,
                    'occurred_at' => $refundedAt,
                ]);
            }

            $this->audit->log('payment', 'refund', 'payment', "Estorno do pagamento {$payment->id}");

            return $payment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applyRefundWebhook(
        Payment $payment,
        Order $order,
        ?string $gatewayName,
        array $metadata,
    ): void {
        $refundedAt = Carbon::parse(
            $metadata['date_last_updated']
                ?? $metadata['date_created']
                ?? now(),
        );

        $amount = $metadata['transaction_amount_refunded']
            ?? $metadata['transaction_amount']
            ?? null;

        $refund = is_numeric($amount) && (float) $amount > 0
            ? $payment->refunds()->create([
                'amount' => round((float) $amount, 2),
                'reason' => 'Estorno informado pelo gateway.',
                'status' => RefundStatus::Refunded->value,
                'refunded_at' => $refundedAt,
            ])
            : null;

        $payment = $this->payments->update($payment, [
            'status' => PaymentStatus::Refunded->value,
        ]);
        $order->update(['status' => OrderStatus::Refunded->value]);

        $this->orderEvents->record($order, OrderEventType::PaymentRefunded, [
            'payment_id' => $payment->id,
            'status' => PaymentStatus::Refunded->value,
            'description' => 'O gateway informou o estorno deste pagamento.',
            'actor_type' => OrderEventActorType::Gateway->value,
            'actor_name' => $gatewayName,
            'source_type' => $refund ? 'refund' : 'payment',
            'source_id' => (int) ($refund?->id ?? $payment->id),
            'occurred_at' => $refundedAt,
            'metadata' => [
                'payment_id' => $payment->id,
                'amount' => $refund ? (float) $refund->amount : null,
            ],
        ]);

        $this->orderEvents->record($order, OrderEventType::OrderRefunded, [
            'status' => OrderStatus::Refunded->value,
            'description' => 'O valor da compra foi devolvido.',
            'actor_type' => OrderEventActorType::Gateway->value,
            'actor_name' => $gatewayName,
            'source_type' => 'order',
            'source_id' => (int) $order->id,
            'occurred_at' => $refundedAt,
        ]);
    }

    private function applyFailedWebhook(
        Payment $payment,
        Order $order,
        PaymentEvent $paymentEvent,
        ?string $gatewayName,
    ): void {
        $this->payments->update($payment, ['status' => PaymentStatus::Failed->value]);

        $this->orderEvents->record($order, OrderEventType::PaymentFailed, [
            'payment_id' => $payment->id,
            'status' => PaymentStatus::Failed->value,
            'description' => 'O gateway recusou esta tentativa de pagamento.',
            'actor_type' => OrderEventActorType::Gateway->value,
            'actor_name' => $gatewayName,
            'source_type' => 'payment',
            'source_id' => (int) $payment->id,
            'occurred_at' => $paymentEvent->created_at,
            'metadata' => [
                'payment_id' => $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
            ],
        ]);
    }

    /**
     * Applies a normalized gateway webhook payload. Idempotent.
     *
     * @param  array{external_id: ?string, event: string, status: string, transaction_id: ?string, paid_at?: ?string, metadata: array}  $payload
     */
    public function applyWebhook(int $paymentId, array $payload): string
    {
        $payment = $this->payments->findAcrossCompanies($paymentId);

        if (! $payment) {
            throw new ResourceNotFoundException('Pagamento não encontrado para o webhook.');
        }

        app(CompanyContext::class)->set((int) $payment->company_id);

        return DB::transaction(function () use ($payment, $payload) {
            $externalId = $payload['external_id'] ?? null;

            if (! empty($externalId)) {
                $exists = PaymentEvent::query()
                    ->where('payment_id', $payment->id)
                    ->where('external_id', $externalId)
                    ->exists();

                if ($exists) {
                    return PaymentEventStatus::Duplicated->value;
                }
            }

            if (empty($payment->transaction_id) && ! empty($payload['transaction_id'])) {
                $payment = $this->payments->update($payment, [
                    'transaction_id' => $payload['transaction_id'],
                ]);
            }

            $paymentEvent = PaymentEvent::query()->create([
                'payment_id' => $payment->id,
                'status' => PaymentEventStatus::Received->value,
                'event' => $payload['event'],
                'external_id' => ! empty($externalId) ? $externalId : null,
                'description' => 'Webhook recebido.',
                'metadata' => $payload['metadata'] ?? [],
            ]);

            $order = $payment->order()->firstOrFail();
            $gatewayName = $payment->gateway?->name;

            match ($payload['status']) {
                PaymentStatus::Paid->value => $this->confirm($payment, [
                    'source' => 'webhook',
                    'paid_at' => $payload['paid_at'] ?? null,
                ]),
                PaymentStatus::Refunded->value => $this->applyRefundWebhook(
                    $payment,
                    $order,
                    $gatewayName,
                    (array) ($payload['metadata'] ?? []),
                ),
                PaymentStatus::Failed->value => $this->applyFailedWebhook(
                    $payment,
                    $order,
                    $paymentEvent,
                    $gatewayName,
                ),
                default => null,
            };

            return PaymentEventStatus::Processed->value;
        });
    }
}
