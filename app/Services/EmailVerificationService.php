<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmailVerificationService
{
    public const CODE_TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly DatalogService $audit,
        private readonly CompanyMailService $mail,
    ) {}

    public function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        EmailVerificationCode::query()
            ->withoutGlobalScope('company')
            ->updateOrCreate(
                [
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                ],
                [
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                    'attempts' => 0,
                    'last_sent_at' => now(),
                ],
            );

        $this->mail->sendVerificationCode($user, $code, self::CODE_TTL_MINUTES);

        $this->audit->log(
            'auth',
            'email_verification_sent',
            'user',
            'Código de confirmação de e-mail enviado',
            $user->id,
        );
    }

    public function resendForEmail(string $email, int $companyId): void
    {
        $user = $this->findUser($email, $companyId);

        if (! $user || $user->email_verified_at !== null) {
            return;
        }

        $record = EmailVerificationCode::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();

        if ($record?->last_sent_at?->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            return;
        }

        $this->sendCode($user);
    }

    public function verifyForEmail(string $email, string $code, int $companyId): User
    {
        $user = $this->findUser($email, $companyId);

        if (! $user || $user->email_verified_at !== null) {
            throw $this->invalidCode();
        }

        $record = EmailVerificationCode::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();

        if (! $record || $record->attempts >= self::MAX_ATTEMPTS || $record->expires_at->isPast()) {
            throw $this->invalidCode();
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            throw $this->invalidCode();
        }

        DB::transaction(function () use ($user, $record) {
            $user->forceFill(['email_verified_at' => now()])->save();
            $record->delete();

            $this->audit->log(
                'auth',
                'email_verified',
                'user',
                'E-mail confirmado pelo aluno',
                $user->id,
            );
        });

        return $user->refresh();
    }

    private function findUser(string $email, int $companyId): ?User
    {
        return User::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('email', $email)
            ->first();
    }

    private function invalidCode(): ApiException
    {
        return new ApiException(
            'Código inválido ou expirado. Solicite um novo código.',
            422,
            'invalid_verification_code',
        );
    }
}
