<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SellerCommissionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerCommission;
use App\Repositories\SellerCommissionRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SellerCommissionService
{
    public function __construct(
        private readonly SellerCommissionRepository $commissions,
        private readonly DatalogService $audit,
    ) {}

    /**
     * Creates one commission row per order item so product-level reports and
     * partial refunds remain accurate. This method is idempotent.
     */
    public function recordForOrder(Order $order, ?Payment $payment = null): void
    {
        $order->loadMissing(['items', 'seller']);

        if (! $order->seller_id || ! $order->seller || (float) $order->commission_percent <= 0) {
            return;
        }

        $items = $order->items->values();

        if ($items->isEmpty()) {
            return;
        }

        $subtotal = (float) $order->subtotal;
        $total = (float) $order->total;
        $percent = (float) $order->commission_percent;
        $remaining = $total;
        $paidAt = $payment?->paid_at ? Carbon::parse($payment->paid_at) : now();

        foreach ($items as $index => $item) {
            $isLast = $index === $items->count() - 1;

            $base = $isLast
                ? $remaining
                : ($subtotal > 0 ? round((float) $item->total * $total / $subtotal, 2) : 0);

            $base = round(max($base, 0), 2);
            $remaining = round($remaining - $base, 2);
            $amount = round($base * $percent / 100, 2);

            if ($amount <= 0) {
                continue;
            }

            $this->commissions->firstOrCreateForItem($item->id, [
                'company_id' => $order->company_id,
                'seller_id' => $order->seller_id,
                'order_id' => $order->id,
                'payment_id' => $payment?->id,
                'base_amount' => $base,
                'commission_percent' => $percent,
                'amount' => $amount,
                'refunded_amount' => 0,
                'status' => SellerCommissionStatus::Approved->value,
                'approved_at' => $paidAt,
            ]);
        }
    }

    /**
     * Recalculates commissions from the cumulative refunded amount. Partial
     * refunds reduce the net commission; a full refund reverses it.
     */
    public function applyOrderRefunds(Order $order): void
    {
        $order->loadMissing(['payments.refunds', 'sellerCommissions']);

        if ($order->sellerCommissions->isEmpty()) {
            return;
        }

        $refunded = round((float) $order->payments
            ->flatMap(fn (Payment $payment) => $payment->refunds)
            ->sum('amount'), 2);

        $total = (float) $order->total;
        $ratio = $order->status === OrderStatus::Refunded->value
            ? 1.0
            : ($total > 0 ? min(1, $refunded / $total) : 0.0);

        $fullyRefunded = $ratio >= 1;

        foreach ($order->sellerCommissions as $commission) {
            $refundedAmount = round(min((float) $commission->amount, (float) $commission->amount * $ratio), 2);
            $status = $fullyRefunded || $commission->status === SellerCommissionStatus::Reversed->value
                ? SellerCommissionStatus::Reversed->value
                : ($commission->status === SellerCommissionStatus::Paid->value
                    ? SellerCommissionStatus::Paid->value
                    : SellerCommissionStatus::Approved->value);

            $commission->update([
                'refunded_amount' => $refundedAmount,
                'status' => $status,
                'reversed_at' => $fullyRefunded
                    ? ($commission->reversed_at ?? now())
                    : $commission->reversed_at,
            ]);
        }
    }

    public function markPaid(SellerCommission $commission, array $data = []): SellerCommission
    {
        $commission->refresh();

        if (! $commission->isPayable()) {
            throw new BusinessRuleException(
                'Somente comissões aprovadas e com saldo podem ser marcadas como pagas.',
                'commission_not_payable',
            );
        }

        $commission = $this->commissions->update($commission, [
            'status' => SellerCommissionStatus::Paid->value,
            'paid_at' => ! empty($data['paid_at'])
                ? Carbon::parse($data['paid_at'])
                : now(),
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_notes' => $data['payment_notes'] ?? null,
        ]);

        $this->audit->log(
            'seller',
            'commission_paid',
            'seller_commission',
            "Comissão paga: {$commission->id}",
        );

        return $this->commissions->findWithRelations($commission->id);
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function markManyPaid(array $ids, array $data = []): int
    {
        return DB::transaction(function () use ($ids, $data) {
            $count = 0;

            $commissions = SellerCommission::query()
                ->whereIn('id', $ids)
                ->where('status', SellerCommissionStatus::Approved->value)
                ->get();

            foreach ($commissions as $commission) {
                if (! $commission->isPayable()) {
                    continue;
                }

                $this->markPaid($commission, $data);
                $count++;
            }

            return $count;
        });
    }
}
