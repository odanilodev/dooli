<?php

namespace App\Services;

use App\DTO\LoginDTO;
use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\UserType;
use App\Repositories\UserRepository;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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

    /**
     * Registers a new student inside the current tenant and signs them in.
     *
     * @param  array{name: string, email: string, password: string, device_name?: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $companyId = (int) app(CompanyContext::class)->id();

        $studentType = UserType::query()
            ->where('company_id', $companyId)
            ->where('code', UserTypeCode::Student->value)
            ->where('status', GeneralStatus::Active->value)
            ->first();

        if (! $studentType) {
            throw new ApiException('Cadastro indisponível para esta empresa.', 422, 'registration_unavailable');
        }

        $user = $this->users->create([
            'company_id' => $companyId,
            'user_type_id' => $studentType->id,
            'name' => $data['name'],
            'username' => $this->uniqueUsername($data['email'], $companyId),
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => GeneralStatus::Active->value,
        ]);

        $token = $user->createToken($data['device_name'] ?? 'api', ['student'])->plainTextToken;

        $this->audit->log('auth', 'register', 'user', 'Novo aluno cadastrado', $user->id);

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

    private function uniqueUsername(string $email, int $companyId): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'aluno';
        $username = $base;
        $suffix = 2;

        while (User::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('username', $username)
            ->exists()) {
            $username = $base.'-'.$suffix;
            $suffix++;
        }

        return $username;
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
