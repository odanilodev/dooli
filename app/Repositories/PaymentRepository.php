<?php

namespace App\Repositories;

use App\Models\Payment;

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
        return $this->query()->with(['events', 'refunds', 'order', 'gateway'])->findOrFail($id);
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
