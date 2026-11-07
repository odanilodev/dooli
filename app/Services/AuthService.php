<?php

namespace App\Services;

use App\DTO\LoginDTO;
use App\Enums\GeneralStatus;
use App\Enums\UserRegistrationSource;
use App\Enums\UserTypeCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\UserType;
use App\Repositories\UserRepository;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DatalogService $audit,
        private readonly EmailVerificationService $emailVerification,
        private readonly SellerAttributionService $sellerAttribution,
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

        $this->assertCanAuthenticate($user);

        return $this->authenticate($user, $dto->deviceName);
    }

    /**
     * Registers a new student inside the current tenant and sends the e-mail
     * confirmation code. The account is not authenticated until verified.
     *
     * @param  array{name: string, email: string, password: string, referral_code?: string|null}  $data
     */
    public function register(array $data): User
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

        // Cadastro anterior da loja que nunca confirmou o e-mail: reaproveita a conta
        // (nova senha e dados) e reenvia o código, em vez de bloquear com "já existe".
        $user = User::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('email', $data['email'])
            ->whereNull('email_verified_at')
            ->where('registration_source', UserRegistrationSource::Store->value)
            ->first();

        if ($user) {
            $user->forceFill([
                'name' => $data['name'],
                'password' => $data['password'],
            ])->save();

            $this->audit->log('auth', 'register_retry', 'user', 'Cadastro não confirmado retomado', $user->id);
        } else {
            $user = $this->users->create([
                'company_id' => $companyId,
                'user_type_id' => $studentType->id,
                'name' => $data['name'],
                'username' => $this->uniqueUsername($data['email'], $companyId),
                'email' => $data['email'],
                'email_verified_at' => null,
                'password' => $data['password'],
                'status' => GeneralStatus::Active->value,
                'registration_source' => UserRegistrationSource::Store->value,
            ]);

            $this->audit->log('auth', 'register', 'user', 'Novo aluno cadastrado', $user->id);
        }

        $this->sellerAttribution->rememberFromCode($user, $data['referral_code'] ?? null);

        try {
            $this->emailVerification->sendCode($user);
        } catch (Throwable $e) {
            report($e);

            $this->audit->log(
                'auth',
                'email_verification_failed',
                'user',
                'Falha ao enviar o código de confirmação de e-mail',
                $user->id,
            );
        }

        return $user->load('userType');
    }

    /**
     * Verifies the e-mail code and signs the user in.
     *
     * @param  array{email: string, code: string, device_name?: string}  $data
     * @return array{user: User, token: string}
     */
    public function verifyEmail(array $data): array
    {
        $companyId = (int) app(CompanyContext::class)->id();

        $user = $this->emailVerification->verifyForEmail(
            $data['email'],
            $data['code'],
            $companyId,
        );

        $this->assertCanAuthenticate($user);

        return $this->authenticate($user, $data['device_name'] ?? 'api');
    }

    public function resendVerification(string $email): void
    {
        $this->emailVerification->resendForEmail(
            $email,
            (int) app(CompanyContext::class)->id(),
        );
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
     * Inactive accounts/companies and unverified e-mails cannot receive a token.
     * The first two cases use the generic credentials error to avoid enumeration.
     */
    private function assertCanAuthenticate(User $user): void
    {
        if ($user->status !== GeneralStatus::Active->value) {
            $this->audit->log('auth', 'login_blocked', 'user', 'Login bloqueado: conta inativa', $user->id);

            throw new ApiException('Credenciais inválidas.', 401, 'invalid_credentials');
        }

        if ($user->company && $user->company->status !== GeneralStatus::Active->value) {
            $this->audit->log('auth', 'login_blocked', 'user', 'Login bloqueado: empresa inativa', $user->id);

            throw new ApiException('Credenciais inválidas.', 401, 'invalid_credentials');
        }

        if ($user->email_verified_at === null) {
            $this->audit->log('auth', 'login_blocked', 'user', 'Login bloqueado: e-mail não verificado', $user->id);

            throw new ApiException(
                'Confirme seu e-mail para entrar.',
                403,
                'email_not_verified',
            );
        }
    }

    /**
     * @return array{user: User, token: string}
     */
    private function authenticate(User $user, string $deviceName): array
    {
        $abilities = $user->hasRole(UserTypeCode::Admin->value, UserTypeCode::Instructor->value)
            ? ['admin']
            : ['student'];

        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        $this->audit->log('auth', 'login', 'user', 'Login realizado', $user->id);

        return ['user' => $user->load('userType'), 'token' => $token];
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
