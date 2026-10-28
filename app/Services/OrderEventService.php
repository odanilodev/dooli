<?php

namespace App\Services;

use App\Enums\OrderEventActorType;
use App\Enums\OrderEventType;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Support\Carbon;

class OrderEventService
{
    /**
     * Record an immutable business event for an order. Re-recording the same
     * type/source updates that event instead of duplicating it.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Order $order, OrderEventType $type, array $data = []): OrderEvent
    {
        $actor = $this->resolveActor($order, $data);
        $sourceType = $data['source_type'] ?? 'order';
        $sourceId = (int) ($data['source_id'] ?? $order->id);

        return OrderEvent::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'type' => $type->value,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'company_id' => $order->company_id,
                'payment_id' => $data['payment_id'] ?? null,
                'status' => $data['status'] ?? null,
                'title' => $data['title'] ?? $this->title($type),
                'description' => $data['description'] ?? null,
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'actor_name' => $actor['name'],
                'metadata' => $data['metadata'] ?? null,
                'occurred_at' => isset($data['occurred_at'])
                    ? Carbon::parse($data['occurred_at'])
                    : now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int|null, name: string|null}
     */
    private function resolveActor(Order $order, array $data): array
    {
        if (! empty($data['actor_type'])) {
            return [
                'type' => (string) $data['actor_type'],
                'id' => isset($data['actor_id']) ? (int) $data['actor_id'] : null,
                'name' => $data['actor_name'] ?? null,
            ];
        }

        $user = auth()->user();

        if (! $user || ! isset($user->id)) {
            return ['type' => OrderEventActorType::System->value, 'id' => null, 'name' => null];
        }

        $isStudent = (int) $order->user_id === (int) $user->id;

        return [
            'type' => $isStudent
                ? OrderEventActorType::Student->value
                : OrderEventActorType::Admin->value,
            'id' => (int) $user->id,
            'name' => $user->name ?? null,
        ];
    }

    private function title(OrderEventType $type): string
    {
        return match ($type) {
            OrderEventType::OrderCreated => 'Pedido criado',
            OrderEventType::OrderCanceled => 'Pedido cancelado',
            OrderEventType::OrderRefunded => 'Pedido estornado',
            OrderEventType::PaymentStarted => 'Pagamento iniciado',
            OrderEventType::PaymentApproved => 'Pagamento aprovado',
            OrderEventType::PaymentFailed => 'Pagamento recusado',
            OrderEventType::PaymentRefunded => 'Estorno realizado',
            OrderEventType::PaymentBlocked => 'Confirmação bloqueada',
            OrderEventType::AccessGranted => 'Acesso liberado',
        };
    }
}
