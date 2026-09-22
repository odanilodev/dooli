<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository extends BaseRepository
{
    protected array $filterable = ['status', 'team_id', 'type_notification_id', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new Notification);
    }

    public function forUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->with([
                'type',
                // Load only this user's pivot so read_at is available in the resource.
                'users' => fn ($q) => $q->where('users.id', $userId),
            ])
            ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->where(function ($q) {
                $q->whereNull('release_at')->orWhere('release_at', '<=', now());
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
