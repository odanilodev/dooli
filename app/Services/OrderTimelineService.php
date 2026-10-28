<?php

namespace App\Services;

use App\Enums\AccessGrantSource;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AccessGrant;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrderTimelineService
{
    /**
     * Merge persistent order events with events reconstructed from legacy
     * records so old orders also get a useful history.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forOrder(Order $order): Collection
    {
        $order->loadMissing(['events', 'payments.events', 'payments.refunds', 'accessGrants']);

        $events = $order->events
            ->map(fn (OrderEvent $event) => $this->fromEvent($event))
            ->values();

        $keys = $events
            ->mapWithKeys(fn (array $event) => [$this->key($event['type'], $event['source_type'], $event['source_id']) => true])
            ->all();

        $this->addLegacyOrderEvents($order, $events, $keys);

        foreach ($order->payments as $payment) {
            $this->addLegacyPaymentEvents($order, $payment, $events, $keys);
        }

        if ($order->subscription_id) {
            $this->addLegacySubscriptionAccess($order, $events, $keys);
        }

        if ($order->status === OrderStatus::Refunded->value) {
            $this->addLegacyRefundedOrder($order, $events, $keys);
        }

        return $events
            ->sortBy(fn (array $event) => sprintf(
                '%013d-%010d',
                $event['occurred_at'] ? Carbon::parse($event['occurred_at'])->getTimestampMs() : 9999999999999,
                (int) ($event['id'] ?? 0),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @param  array<string, bool>  $keys
     */
    private function addLegacyOrderEvents(Order $order, Collection $events, array &$keys): void
    {
        $this->pushSynthetic(
            $events,
            $keys,
            OrderEventType::OrderCreated->value,
            'Pedido criado',
            'Pedido registrado no sistema.',
            $order->created_at,
            $order->status,
            'order',
            (int) $order->id,
        );

        if ($order->status === OrderStatus::Canceled->value) {
            $this->pushSynthetic(
                $events,
                $keys,
                OrderEventType::OrderCanceled->value,
                'Pedido cancelado',
                'O pedido foi encerrado antes da confirmação do pagamento.',
                $order->updated_at,
                $order->status,
                'order',
                (int) $order->id,
            );
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @param  array<string, bool>  $keys
     */
    private function addLegacyPaymentEvents(Order $order, Payment $payment, Collection $events, array &$keys): void
    {
        $key = $this->key(OrderEventType::PaymentStarted->value, 'payment', $payment->id);

        if (! isset($keys[$key])) {
            $this->pushSynthetic(
                $events,
                $keys,
                OrderEventType::PaymentStarted->value,
                'Pagamento iniciado',
                $this->paymentStartedDescription($payment),
                $payment->created_at,
                PaymentStatus::Pending->value,
                'payment',
                (int) $payment->id,
                ['payment_id' => $payment->id, 'method' => $payment->method],
            );
        }

        if (in_array($payment->status, [PaymentStatus::Paid->value, PaymentStatus::Refunded->value], true)) {
            $key = $this->key(OrderEventType::PaymentApproved->value, 'payment', $payment->id);

            if (! isset($keys[$key])) {
                $this->pushSynthetic(
                    $events,
                    $keys,
                    OrderEventType::PaymentApproved->value,
                    'Pagamento aprovado',
                    'O pagamento foi confirmado pelo gateway.',
                    $payment->paid_at ?? $payment->updated_at,
                    PaymentStatus::Paid->value,
                    'payment',
                    (int) $payment->id,
                    ['payment_id' => $payment->id, 'method' => $payment->method],
                );
            }
        }

        if ($payment->status === PaymentStatus::Failed->value) {
            $key = $this->key(OrderEventType::PaymentFailed->value, 'payment', $payment->id);

            if (! isset($keys[$key])) {
                $this->pushSynthetic(
                    $events,
                    $keys,
                    OrderEventType::PaymentFailed->value,
                    'Pagamento recusado',
                    'O gateway recusou esta tentativa de pagamento.',
                    $payment->updated_at,
                    PaymentStatus::Failed->value,
                    'payment',
                    (int) $payment->id,
                    ['payment_id' => $payment->id, 'method' => $payment->method],
                );
            }
        }

        foreach ($payment->refunds as $refund) {
            $key = $this->key(OrderEventType::PaymentRefunded->value, 'refund', $refund->id);

            if (! isset($keys[$key])) {
                $this->pushSynthetic(
                    $events,
                    $keys,
                    OrderEventType::PaymentRefunded->value,
                    'Estorno realizado',
                    $refund->reason ?: 'O valor foi devolvido ao comprador.',
                    $refund->refunded_at ?? $refund->created_at,
                    PaymentStatus::Refunded->value,
                    'refund',
                    (int) $refund->id,
                    ['payment_id' => $payment->id, 'amount' => (float) $refund->amount],
                );
            }
        }

        if ($payment->status === PaymentStatus::Refunded->value && $payment->refunds->isEmpty()) {
            $key = $this->key(OrderEventType::PaymentRefunded->value, 'payment', $payment->id);

            if (! isset($keys[$key])) {
                $this->pushSynthetic(
                    $events,
                    $keys,
                    OrderEventType::PaymentRefunded->value,
                    'Estorno realizado',
                    'O gateway informou o estorno deste pagamento.',
                    $payment->updated_at,
                    PaymentStatus::Refunded->value,
                    'payment',
                    (int) $payment->id,
                    ['payment_id' => $payment->id],
                );
            }
        }

        if (in_array($payment->status, [PaymentStatus::Paid->value, PaymentStatus::Refunded->value], true)) {
            $this->addLegacyAccessGranted($order, $payment, $events, $keys);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @param  array<string, bool>  $keys
     */
    private function addLegacyAccessGranted(Order $order, Payment $payment, Collection $events, array &$keys): void
    {
        $key = $this->key(OrderEventType::AccessGranted->value, 'order', $order->id);

        if (isset($keys[$key])) {
            return;
        }

        $grant = $order->accessGrants->first();

        $this->pushSynthetic(
            $events,
            $keys,
            OrderEventType::AccessGranted->value,
            'Acesso liberado',
            'O conteúdo da compra foi disponibilizado na área do aluno.',
            $grant?->starts_at ?? $grant?->created_at ?? $payment->paid_at ?? $payment->updated_at,
            $order->status,
            'order',
            (int) $order->id,
            ['payment_id' => $payment->id],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @param  array<string, bool>  $keys
     */
    private function addLegacySubscriptionAccess(Order $order, Collection $events, array &$keys): void
    {
        if ($this->hasEventType($keys, OrderEventType::AccessGranted->value)) {
            return;
        }

        $grant = AccessGrant::query()
            ->where('source_type', AccessGrantSource::Subscription->value)
            ->where('source_id', $order->subscription_id)
            ->orderBy('created_at')
            ->first();

        if (! $grant) {
            return;
        }

        $this->pushSynthetic(
            $events,
            $keys,
            OrderEventType::AccessGranted->value,
            'Acesso liberado',
            'O conteúdo da assinatura foi disponibilizado na área do aluno.',
            $grant->starts_at ?? $grant->created_at,
            $order->status,
            'subscription',
            (int) $order->subscription_id,
            [],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @param  array<string, bool>  $keys
     */
    private function addLegacyRefundedOrder(Order $order, Collection $events, array &$keys): void
    {
        $key = $this->key(OrderEventType::OrderRefunded->value, 'order', $order->id);

        if (isset($keys[$key])) {
            return;
        }

        $refundedAt = $order->payments
            ->flatMap(fn (Payment $payment) => $payment->refunds)
            ->max('refunded_at') ?? $order->updated_at;

        $this->pushSynthetic(
            $events,
            $keys,
            OrderEventType::OrderRefunded->value,
            'Pedido estornado',
            'O valor da compra foi devolvido.',
            $refundedAt,
            $order->status,
            'order',
            (int) $order->id,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @param  array<string, bool>  $keys
     * @param  array<string, mixed>  $metadata
     */
    private function pushSynthetic(
        Collection $events,
        array &$keys,
        string $type,
        string $title,
        ?string $description,
        mixed $occurredAt,
        ?string $status,
        ?string $sourceType,
        ?int $sourceId,
        array $metadata = [],
    ): void {
        $key = $this->key($type, $sourceType, $sourceId);

        if (isset($keys[$key])) {
            return;
        }

        $keys[$key] = true;

        $events->push([
            'id' => null,
            'payment_id' => $metadata['payment_id'] ?? null,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'actor_type' => 'system',
            'actor_name' => null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt instanceof Carbon
                ? $occurredAt->toIso8601String()
                : ($occurredAt ? Carbon::parse($occurredAt)->toIso8601String() : null),
            'estimated' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromEvent(OrderEvent $event): array
    {
        return [
            'id' => $event->id,
            'payment_id' => $event->payment_id,
            'type' => $event->type,
            'title' => $event->title,
            'description' => $event->description,
            'status' => $event->status,
            'actor_type' => $event->actor_type,
            'actor_name' => $event->actor_name,
            'source_type' => $event->source_type,
            'source_id' => $event->source_id,
            'metadata' => $event->metadata,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'estimated' => false,
        ];
    }

    private function key(?string $type, ?string $sourceType, ?int $sourceId): string
    {
        return implode(':', [$type, $sourceType, $sourceId]);
    }

    /**
     * @param  array<string, bool>  $keys
     */
    private function hasEventType(array $keys, string $type): bool
    {
        foreach (array_keys($keys) as $key) {
            if (str_starts_with($key, $type.':')) {
                return true;
            }
        }

        return false;
    }

    private function paymentStartedDescription(Payment $payment): string
    {
        return match ($payment->method) {
            'pix' => 'Aguardando a confirmação do PIX.',
            'credit_card' => 'Aguardando a confirmação do cartão.',
            default => 'Aguardando a confirmação do gateway.',
        };
    }
}
