<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository extends BaseRepository
{
    protected array $searchable = ['name', 'email', 'username'];

    protected array $filterable = ['status', 'user_type_id', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new User);
    }

    public function findByLogin(string $login): ?User
    {
        return $this->query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();
    }

    public function findWithRelations(int $id): User
    {
        return $this->query()->with(['userType', 'teams'])->findOrFail($id);
    }
}
