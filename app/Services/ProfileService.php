<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;

class ProfileService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DatalogService $audit,
    ) {}

    public function update(User $user, array $data): User
    {
        $updated = $this->users->update($user, $data);

        $this->audit->log('profile', 'update', 'user', 'Perfil atualizado', $user->id);

        return $updated->load('userType');
    }

    /**
     * Updates the password and revokes every token except the current one.
     */
    public function updatePassword(User $user, string $password): void
    {
        $this->users->update($user, ['password' => $password]);

        $currentTokenId = $user->currentAccessToken()?->id;

        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        } else {
            $user->tokens()->delete();
        }

        $this->audit->log('profile', 'password_change', 'user', 'Senha alterada', $user->id);
    }
}
