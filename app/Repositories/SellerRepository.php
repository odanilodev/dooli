<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\Seller;

class SellerRepository extends BaseRepository
{
    protected array $searchable = ['name', 'email', 'code'];

    protected array $filterable = ['status', 'company_id', 'user_id'];

    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Seller);
    }

    public function findWithRelations(int $id): Seller
    {
        return $this->query()
            ->with(['user', 'company'])
            ->findOrFail($id);
    }

    public function findActiveByCode(string $code): ?Seller
    {
        return $this->query()
            ->with('company')
            ->where('code', Seller::normalizeCode($code))
            ->where('status', GeneralStatus::Active->value)
            ->first();
    }

    public function findActiveByShareToken(string $token): ?Seller
    {
        return $this->query()
            ->with('company')
            ->where('share_token', $token)
            ->where('status', GeneralStatus::Active->value)
            ->first();
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        return $this->query()
            ->where('code', Seller::normalizeCode($code))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function tokenExists(string $token): bool
    {
        return $this->query()
            ->withoutGlobalScope('company')
            ->where('share_token', $token)
            ->exists();
    }
}
