<?php

namespace App\Repositories;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;

class PaymentRepository extends BaseRepository
{
    protected array $searchable = ['transaction_id', 'status'];

    protected array $filterable = ['status', 'order_id', 'gateway_id', 'company_id', 'method'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Payment);
    }

    public function findWithEvents(int $id): Payment
    {
        return $this->query()
            ->with(['events', 'refunds', 'order.user', 'gateway'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int|array<string, int>>
     */
    public function summary(array $filters): array
    {
        $query = $this->query();
        $this->applyFilters($query, $filters);

        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $counts = [];
        $amounts = [];

        foreach (PaymentStatus::values() as $status) {
            $counts[$status] = (int) ($rows[$status]->total_count ?? 0);
            $amounts[$status] = round((float) ($rows[$status]->total_amount ?? 0), 2);
        }

        $refunded = round((float) Refund::query()
            ->whereIn('payment_id', (clone $query)->select('payments.id'))
            ->where('status', RefundStatus::Refunded->value)
            ->sum('amount'), 2);

        $gross = round(
            ($amounts[PaymentStatus::Paid->value] ?? 0)
            + ($amounts[PaymentStatus::Refunded->value] ?? 0),
            2,
        );

        return [
            'received' => round(max($gross - $refunded, 0), 2),
            'gross' => $gross,
            'refunded' => $refunded,
            'pending' => $amounts[PaymentStatus::Pending->value] ?? 0,
            'failed' => $amounts[PaymentStatus::Failed->value] ?? 0,
            'counts' => $counts,
        ];
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return $this->query()->where('transaction_id', $transactionId)->first();
    }

    /**
     * Webhooks are unauthenticated, so the tenant is not known yet. The
     * payment is resolved across companies and the caller derives the tenant
     * from it instead of trusting the request.
     */
    public function findByTransactionIdAcrossCompanies(string $transactionId): ?Payment
    {
        return $this->query()
            ->withoutGlobalScope('company')
            ->where('transaction_id', $transactionId)
            ->first();
    }

    public function findAcrossCompanies(int $id): ?Payment
    {
        return $this->query()
            ->withoutGlobalScope('company')
            ->find($id);
    }
}
