<?php

namespace Database\Seeders;

use App\Enums\GatewayCode;
use App\Enums\OrderEventActorType;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentEventStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Refund;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $student = User::query()->where('email', 'aluno@cursos.test')->firstOrFail();

        $mercadopago = Gateway::query()
            ->where('company_id', $company->id)
            ->where('code', GatewayCode::MercadoPago->value)
            ->firstOrFail();

        $manual = Gateway::query()
            ->where('company_id', $company->id)
            ->where('code', GatewayCode::Manual->value)
            ->firstOrFail();

        $coupon = Coupon::query()
            ->where('company_id', $company->id)
            ->where('code', 'BEMVINDO10')
            ->firstOrFail();

        $storeProducts = StoreProduct::query()
            ->where('company_id', $company->id)
            ->whereIn('slug', ['curso-de-php', 'curso-de-php-anual'])
            ->get()
            ->keyBy('slug');

        $orders = [
            [
                'status' => OrderStatus::Paid->value,
                'coupon_id' => $coupon->id,
                'subtotal' => 199.90,
                'discount' => 19.99,
                'total' => 179.91,
                'item' => ['slug' => 'curso-de-php', 'quantity' => 1, 'unit_price' => 199.90],
                'payment' => [
                    'gateway_id' => $mercadopago->id,
                    'method' => 'credit_card',
                    'transaction_id' => 'MP-DEMO-0001',
                    'status' => PaymentStatus::Paid->value,
                    'amount' => 179.91,
                    'paid_at' => now()->subDays(5),
                    'events' => [
                        ['event' => 'payment.created', 'external_id' => 'evt-demo-0001', 'status' => PaymentEventStatus::Received->value],
                        ['event' => 'payment.updated', 'external_id' => 'evt-demo-0002', 'status' => PaymentEventStatus::Processed->value],
                    ],
                    'refund' => null,
                ],
            ],
            [
                'status' => OrderStatus::Pending->value,
                'coupon_id' => null,
                'subtotal' => 999.00,
                'discount' => 0,
                'total' => 999.00,
                'item' => ['slug' => 'curso-de-php-anual', 'quantity' => 1, 'unit_price' => 999.00],
                'payment' => [
                    'gateway_id' => $manual->id,
                    'method' => 'pix',
                    'transaction_id' => 'MANUAL-DEMO-0002',
                    'status' => PaymentStatus::Pending->value,
                    'amount' => 999.00,
                    'paid_at' => null,
                    'events' => [
                        ['event' => 'payment.pending', 'external_id' => 'evt-demo-0003', 'status' => PaymentEventStatus::Received->value],
                    ],
                    'refund' => null,
                ],
            ],
            [
                'status' => OrderStatus::Refunded->value,
                'coupon_id' => null,
                'subtotal' => 199.90,
                'discount' => 0,
                'total' => 199.90,
                'item' => ['slug' => 'curso-de-php', 'quantity' => 1, 'unit_price' => 199.90],
                'payment' => [
                    'gateway_id' => $mercadopago->id,
                    'method' => 'pix',
                    'transaction_id' => 'MP-DEMO-0003',
                    'status' => PaymentStatus::Refunded->value,
                    'amount' => 199.90,
                    'paid_at' => now()->subDays(15),
                    'events' => [
                        ['event' => 'payment.created', 'external_id' => 'evt-demo-0004', 'status' => PaymentEventStatus::Received->value],
                        ['event' => 'payment.updated', 'external_id' => 'evt-demo-0005', 'status' => PaymentEventStatus::Processed->value],
                    ],
                    'refund' => [
                        'amount' => 199.90,
                        'reason' => 'Solicitação do aluno',
                        'status' => RefundStatus::Refunded->value,
                        'refunded_at' => now()->subDays(10),
                    ],
                ],
            ],
        ];

        foreach ($orders as $data) {
            $order = Order::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $student->id,
                    'status' => $data['status'],
                    'coupon_id' => $data['coupon_id'],
                ],
                [
                    'subtotal' => $data['subtotal'],
                    'discount' => $data['discount'],
                    'total' => $data['total'],
                ],
            );

            $storeProduct = $storeProducts->get($data['item']['slug']);

            OrderItem::query()->updateOrCreate(
                ['order_id' => $order->id, 'store_product_id' => $storeProduct->id],
                [
                    'company_id' => $company->id,
                    'product_title' => $storeProduct->title,
                    'product_access_label' => $storeProduct->accessLabel(),
                    'quantity' => $data['item']['quantity'],
                    'unit_price' => $data['item']['unit_price'],
                    'total' => $data['item']['unit_price'] * $data['item']['quantity'],
                ],
            );

            OrderEvent::query()->updateOrCreate(
                [
                    'order_id' => $order->id,
                    'type' => OrderEventType::OrderCreated->value,
                    'source_type' => 'order',
                    'source_id' => $order->id,
                ],
                [
                    'company_id' => $company->id,
                    'status' => OrderStatus::Pending->value,
                    'title' => 'Pedido criado',
                    'description' => 'Pedido registrado no sistema.',
                    'actor_type' => OrderEventActorType::System->value,
                    'occurred_at' => $order->created_at ?? now(),
                    'metadata' => ['total' => (float) $order->total],
                ],
            );

            $payment = Payment::query()->updateOrCreate(
                ['company_id' => $company->id, 'order_id' => $order->id],
                [
                    'gateway_id' => $data['payment']['gateway_id'],
                    'method' => $data['payment']['method'],
                    'transaction_id' => $data['payment']['transaction_id'],
                    'status' => $data['payment']['status'],
                    'amount' => $data['payment']['amount'],
                    'paid_at' => $data['payment']['paid_at'],
                ],
            );

            foreach ($data['payment']['events'] as $event) {
                PaymentEvent::query()->updateOrCreate(
                    ['payment_id' => $payment->id, 'external_id' => $event['external_id']],
                    [
                        'company_id' => $company->id,
                        'status' => $event['status'],
                        'event' => $event['event'],
                        'description' => 'Evento de demonstração.',
                        'metadata' => ['source' => 'seeder'],
                    ],
                );
            }

            $this->paymentEvent(
                $order,
                $payment,
                OrderEventType::PaymentStarted,
                PaymentStatus::Pending->value,
                'Pagamento iniciado',
                'Tentativa de pagamento registrada.',
                $payment->created_at,
            );

            if (in_array($payment->status, [PaymentStatus::Paid->value, PaymentStatus::Refunded->value], true)) {
                $this->paymentEvent(
                    $order,
                    $payment,
                    OrderEventType::PaymentApproved,
                    PaymentStatus::Paid->value,
                    'Pagamento aprovado',
                    'Pagamento confirmado pelo gateway.',
                    $payment->paid_at ?? $payment->updated_at,
                );

                OrderEvent::query()->updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'type' => OrderEventType::AccessGranted->value,
                        'source_type' => 'order',
                        'source_id' => $order->id,
                    ],
                    [
                        'company_id' => $company->id,
                        'payment_id' => $payment->id,
                        'status' => OrderStatus::Paid->value,
                        'title' => 'Acesso liberado',
                        'description' => 'Acesso disponibilizado na área do aluno.',
                        'actor_type' => OrderEventActorType::System->value,
                        'occurred_at' => $payment->paid_at ?? $payment->updated_at,
                    ],
                );
            }

            if ($data['payment']['refund'] !== null) {
                $refund = Refund::query()->updateOrCreate(
                    ['company_id' => $company->id, 'payment_id' => $payment->id],
                    $data['payment']['refund'],
                );

                OrderEvent::query()->updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'type' => OrderEventType::PaymentRefunded->value,
                        'source_type' => 'refund',
                        'source_id' => $refund->id,
                    ],
                    [
                        'company_id' => $company->id,
                        'payment_id' => $payment->id,
                        'status' => PaymentStatus::Refunded->value,
                        'title' => 'Estorno realizado',
                        'description' => $refund->reason ?: 'O valor foi devolvido ao comprador.',
                        'actor_type' => OrderEventActorType::System->value,
                        'occurred_at' => $refund->refunded_at ?? now(),
                        'metadata' => ['amount' => (float) $refund->amount],
                    ],
                );

                OrderEvent::query()->updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'type' => OrderEventType::OrderRefunded->value,
                        'source_type' => 'order',
                        'source_id' => $order->id,
                    ],
                    [
                        'company_id' => $company->id,
                        'status' => OrderStatus::Refunded->value,
                        'title' => 'Pedido estornado',
                        'description' => 'O valor da compra foi devolvido.',
                        'actor_type' => OrderEventActorType::System->value,
                        'occurred_at' => $refund->refunded_at ?? now(),
                    ],
                );
            }
        }
    }

    private function paymentEvent(
        Order $order,
        Payment $payment,
        OrderEventType $type,
        string $status,
        string $title,
        string $description,
        mixed $occurredAt,
    ): void {
        OrderEvent::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'type' => $type->value,
                'source_type' => 'payment',
                'source_id' => $payment->id,
            ],
            [
                'company_id' => $order->company_id,
                'payment_id' => $payment->id,
                'status' => $status,
                'title' => $title,
                'description' => $description,
                'actor_type' => OrderEventActorType::Gateway->value,
                'occurred_at' => $occurredAt ?? now(),
            ],
        );
    }
}
