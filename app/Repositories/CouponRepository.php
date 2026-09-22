<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\Coupon;

class CouponRepository extends BaseRepository
{
    protected array $searchable = ['code'];

    protected array $filterable = ['status', 'type', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Coupon);
    }

    public function findActiveByCode(string $code): ?Coupon
    {
        return $this->query()
            ->where('code', $code)
            ->where('status', GeneralStatus::Active->value)
            ->first();
    }

    public function findWithStoreProducts(int $id): Coupon
    {
        return $this->query()->with('storeProducts')->findOrFail($id);
    }
}
