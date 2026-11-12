<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Enums\NotificationSlug;
use App\Exceptions\ResourceNotFoundException;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TypeNotification;
use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly DatalogService $audit,
    ) {}

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->notifications->forUser($user->id, $perPage);
    }

    /**
     * Creates a team notification and fans it out to the team members.
     *
     * @param  array<int, int>  $userIds  when empty, targets all active team users
     */
    public function createForTeam(
        Team $team,
        NotificationSlug $slug,
        ?string $releaseAt = null,
        array $userIds = [],
    ): Notification {
        return DB::transaction(function () use ($team, $slug, $releaseAt, $userIds) {
            $type = TypeNotification::query()->firstOrCreate(
                ['company_id' => $team->company_id, 'slug' => $slug->value],
                ['name' => $slug->title()],
            );

            $notification = Notification::query()->create([
                'company_id' => $team->company_id,
                'team_id' => $team->id,
                'type_notification_id' => $type->id,
                'title' => $slug->title(),
                'category' => $slug->category(),
                'severity' => $slug->severity(),
                'release_at' => $releaseAt,
                'status' => GeneralStatus::Active->value,
            ]);

            if (empty($userIds)) {
                $userIds = $team->users()
                    ->wherePivot('status', GeneralStatus::Active->value)
                    ->pluck('users.id')
                    ->all();
            }

            if (! empty($userIds)) {
                $notification->users()->syncWithoutDetaching(
                    collect($userIds)->mapWithKeys(fn ($id) => [
                        $id => ['company_id' => $notification->company_id],
                    ])->all(),
                );
            }

            $this->audit->log('notification', 'create', 'notification', "Notificação {$slug->value} criada para a turma {$team->name}");

            return $notification;
        });
    }

    /**
     * Creates a transactional notification for a single user and queues the
     * e-mail delivery. Deduplication is scoped to the company.
     *
     * @param  array{
     *     title?: string,
     *     message?: string,
     *     action_url?: string|null,
     *     category?: string,
     *     severity?: string,
     *     release_at?: string|null,
     *     metadata?: array<string, mixed>|null
     * }  $payload
     */
    public function sendToUser(
        User $user,
        NotificationSlug $slug,
        array $payload = [],
        ?string $dedupeKey = null,
        bool $sendEmail = true,
    ): Notification {
        $notification = DB::transaction(function () use ($user, $slug, $payload, $dedupeKey) {
            $type = TypeNotification::query()
                ->withoutGlobalScope('company')
                ->firstOrCreate(
                    ['company_id' => $user->company_id, 'slug' => $slug->value],
                    ['name' => $slug->title()],
                );

            $attributes = [
                'company_id' => $user->company_id,
                'team_id' => null,
                'type_notification_id' => $type->id,
                'title' => $payload['title'] ?? $slug->title(),
                'message' => $payload['message'] ?? null,
                'action_url' => $payload['action_url'] ?? null,
                'category' => $payload['category'] ?? $slug->category(),
                'severity' => $payload['severity'] ?? $slug->severity(),
                'release_at' => $payload['release_at'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
                'status' => GeneralStatus::Active->value,
            ];

            $notification = $dedupeKey !== null
                ? Notification::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $user->company_id)
                    ->where('dedupe_key', $dedupeKey)
                    ->first()
                : null;

            if (! $notification) {
                $notification = Notification::query()->create([
                    ...$attributes,
                    'dedupe_key' => $dedupeKey,
                ]);
            }

            $notification->users()->syncWithoutDetaching([
                $user->id => ['company_id' => $user->company_id],
            ]);

            return $notification;
        });

        if ($sendEmail && filled($user->email) && $user->acceptsEmailFor($slug)) {
            SendNotificationJob::dispatch($notification->id, $user->id)->afterCommit();
        }

        return $notification;
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $isTargeted = Notification::query()
            ->whereKey($notificationId)
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->exists();

        if (! $isTargeted) {
            throw new ResourceNotFoundException('Notificação não encontrada.');
        }

        DB::table('notification_users')->updateOrInsert(
            ['notification_id' => $notificationId, 'user_id' => $user->id],
            [
                'company_id' => $user->company_id,
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function markAllAsRead(User $user): int
    {
        return DB::table('notification_users')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
