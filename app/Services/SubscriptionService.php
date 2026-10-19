<?php

namespace App\Services;

use App\Enums\AccessGrantSource;
use App\Enums\BillingType;
use App\Enums\GeneralStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Gateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\GatewayManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PaymentService $payments,
        private readonly EnrollmentService $enrollments,
        private readonly AccessGrantService $accessGrants,
        private readonly DatalogService $audit,
    ) {}

    /**
     * @return array{subscription: Subscription, checkout_url: string|null}
     */
    public function start(User $user, StoreProduct $product, Gateway $gateway): array
    {
        if ($product->billing_type !== BillingType::Subscription->value) {
            throw new BusinessRuleException('Este produto não é uma assinatura.', 'product_not_subscription');
        }

        if ($product->release_at?->isFuture()) {
            throw new BusinessRuleException('Produto ainda não disponível para venda.', 'product_not_released');
        }

        if ($product->status !== GeneralStatus::Active->value) {
            throw new BusinessRuleException('Produto indisponível.', 'product_unavailable');
        }

        $alreadyActive = Subscription::query()
            ->where('user_id', $user->id)
            ->where('store_product_id', $product->id)
            ->whereIn('status', [SubscriptionStatus::Pending->value, SubscriptionStatus::Active->value])
            ->exists();

        if ($alreadyActive) {
            throw new BusinessRuleException('Você já possui uma assinatura para este produto.', 'subscription_already_active');
        }

        $subscription = Subscription::query()->create([
            'company_id' => $product->company_id,
            'user_id' => $user->id,
            'store_product_id' => $product->id,
            'gateway_id' => $gateway->id,
            'status' => SubscriptionStatus::Pending->value,
            'amount' => $product->effectivePrice(),
        ]);

        $checkoutUrl = null;

        $adapter = $this->gateways->for($gateway);

        if (method_exists($adapter, 'createSubscription')) {
            $result = $adapter->createSubscription($subscription, $gateway);

            $subscription->update([
                'provider_subscription_id' => $result['id'] ?? null,
                'metadata' => $result['raw'] ?? null,
            ]);

            $checkoutUrl = $result['init_point'] ?? null;
        }

        $this->audit->log('subscription', 'create', 'subscription', "Assinatura criada: {$subscription->id}");

        return [
            'subscription' => $subscription->refresh()->load(['storeProduct', 'gateway']),
            'checkout_url' => $checkoutUrl,
        ];
    }

    public function activate(
        Subscription $subscription,
        ?Carbon $paidAt = null,
        ?string $transactionId = null,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
    ): Subscription {
        $paidAt ??= now();
        $periodStart ??= $paidAt->copy();
        $periodEnd ??= $periodStart->copy()->addMonthNoOverflow();

        $subscription = DB::transaction(function () use ($subscription, $paidAt, $transactionId, $periodStart, $periodEnd) {
            $subscription = $subscription->refresh();

            $subscription->update([
                'status' => SubscriptionStatus::Active->value,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'next_billing_at' => $periodEnd,
                'grace_ends_at' => null,
                'canceled_at' => null,
            ]);

            $this->recordCharge(
                $subscription,
                $paidAt,
                $transactionId,
                $periodStart,
                $periodEnd,
            );

            return $subscription->refresh();
        });

        $this->audit->log('subscription', 'activate', 'subscription', "Assinatura ativada: {$subscription->id}");

        return $subscription;
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Canceled->value,
            'canceled_at' => now(),
        ]);

        if (! $subscription->current_period_end) {
            $this->accessGrants->revokeSource(AccessGrantSource::Subscription->value, (int) $subscription->id);
        }

        $this->audit->log('subscription', 'cancel', 'subscription', "Assinatura cancelada: {$subscription->id}");

        return $subscription->refresh();
    }

    public function markPastDue(Subscription $subscription): Subscription
    {
        $base = $subscription->current_period_end ?? now();
        $graceEndsAt = $base->copy()->addDays((int) $subscription->storeProduct->grace_days);

        $subscription->update([
            'status' => SubscriptionStatus::PastDue->value,
            'grace_ends_at' => $graceEndsAt,
        ]);

        $this->accessGrants->extendSource(
            AccessGrantSource::Subscription->value,
            (int) $subscription->id,
            $graceEndsAt,
        );

        $this->audit->log('subscription', 'past_due', 'subscription', "Assinatura em atraso: {$subscription->id}");

        return $subscription->refresh();
    }

    public function handleWebhook(Subscription $subscription, array $payload): Subscription
    {
        $status = $payload['status'] ?? null;
        $paidAt = ! empty($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : now();
        $transactionId = $payload['transaction_id'] ?? null;

        if ($status === 'paid' || $status === 'authorized' || $status === 'active') {
            if ($transactionId) {
                $exists = Payment::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $subscription->company_id)
                    ->where('transaction_id', $transactionId)
                    ->exists();

                if ($exists) {
                    return $subscription->refresh();
                }
            }

            return $this->activate($subscription, $paidAt, $transactionId);
        }

        if ($status === 'canceled' || $status === 'cancelled') {
            return $this->cancel($subscription);
        }

        if (in_array($status, ['past_due', 'rejected', 'failed'], true)) {
            return $this->markPastDue($subscription);
        }

        return $subscription->refresh();
    }

    private function recordCharge(
        Subscription $subscription,
        Carbon $paidAt,
        ?string $transactionId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): void {
        $product = $subscription->storeProduct;

        if (! $product) {
            return;
        }

        $order = Order::query()->create([
            'company_id' => $subscription->company_id,
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'status' => OrderStatus::Pending->value,
            'subtotal' => $subscription->amount,
            'discount' => 0,
            'total' => $subscription->amount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $order->items()->create([
            'company_id' => $subscription->company_id,
            'store_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $subscription->amount,
            'total' => $subscription->amount,
        ]);

        $payment = Payment::query()->create([
            'company_id' => $subscription->company_id,
            'order_id' => $order->id,
            'gateway_id' => $subscription->gateway_id,
            'method' => 'credit_card',
            'transaction_id' => $transactionId,
            'status' => PaymentStatus::Pending->value,
            'amount' => $subscription->amount,
            'paid_at' => $paidAt,
        ]);

        $this->payments->confirm($payment, ['source' => 'subscription_webhook']);
    }
}
