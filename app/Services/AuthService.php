<?php

namespace App\Services;

use App\DTO\LoginDTO;
use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DatalogService $audit,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function login(LoginDTO $dto): array
    {
        $user = $this->users->findByLogin($dto->login);

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw new ApiException('Credenciais inválidas.', 401, 'invalid_credentials');
        }

        // Inactive accounts/companies are rejected with the same generic
        // response as invalid credentials to avoid account enumeration.
        if ($user->status !== GeneralStatus::Active->value) {
            $this->audit->log('auth', 'login_blocked', 'user', 'Login bloqueado: conta inativa', $user->id);

            throw new ApiException('Credenciais inválidas.', 401, 'invalid_credentials');
        }

        if ($user->company && $user->company->status !== GeneralStatus::Active->value) {
            $this->audit->log('auth', 'login_blocked', 'user', 'Login bloqueado: empresa inativa', $user->id);

            throw new ApiException('Credenciais inválidas.', 401, 'invalid_credentials');
        }

        $abilities = $user->hasRole(UserTypeCode::Admin->value, UserTypeCode::Instructor->value)
            ? ['admin']
            : ['student'];

        $token = $user->createToken($dto->deviceName, $abilities)->plainTextToken;

        $this->audit->log('auth', 'login', 'user', 'Login realizado', $user->id);

        return ['user' => $user->load('userType'), 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();

        $this->audit->log('auth', 'logout', 'user', 'Logout realizado', $user->id);
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();

        $this->audit->log('auth', 'logout_all', 'user', 'Logout em todos os dispositivos', $user->id);
    }

    /**
     * Sends a password reset link. Returns the broker status string.
     */
    public function sendResetLink(string $email): string
    {
        return Password::broker()->sendResetLink(['email' => $email]);
    }

    /**
     * Resets the password using a valid token. Returns the broker status string.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     */
    public function resetPassword(array $data): string
    {
        return Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->save();

            // Any existing session is invalidated after a password change.
            $user->tokens()->delete();

            $this->audit->log('auth', 'password_reset', 'user', 'Senha redefinida', $user->id);
        });
    }
}
