<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Throwable;

class UserService
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @param  array<int, int>  $userIds
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    public function bulkSetStatus(array $userIds, string $status): array
    {
        return $this->runBulk($userIds, function (User $user) use ($status) {
            $this->users->update($user, ['status' => $status]);
        });
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    public function bulkDelete(array $userIds, ?int $exceptUserId = null): array
    {
        return $this->runBulk($userIds, function (User $user) {
            $this->users->delete($user);
        }, $exceptUserId);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    private function runBulk(array $ids, callable $worker, ?int $exceptUserId = null): array
    {
        $processed = 0;
        $failed = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($exceptUserId !== null && $id === $exceptUserId) {
                $failed[] = ['id' => $id, 'name' => null, 'reason' => 'Não é possível executar esta ação em si mesmo.'];

                continue;
            }

            $user = User::query()->find($id);

            if (! $user) {
                $failed[] = ['id' => $id, 'name' => null, 'reason' => 'Usuário não encontrado.'];

                continue;
            }

            try {
                $worker($user);
                $processed++;
            } catch (Throwable $e) {
                $failed[] = ['id' => $id, 'name' => $user->name, 'reason' => $e->getMessage()];
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }
}
