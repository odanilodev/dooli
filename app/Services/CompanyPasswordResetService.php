<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CompanyPasswordResetService
{
    public function send(string $email, int $companyId): string
    {
        $user = User::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('email', $email)
            ->first();

        if (! $user) {
            return Password::RESET_LINK_SENT;
        }

        $existing = DB::table('company_password_reset_tokens')
            ->where('company_id', $companyId)
            ->where('email', $email)
            ->first();

        if ($existing && $existing->created_at && now()->diffInSeconds($existing->created_at) < 60) {
            return Password::RESET_THROTTLED;
        }

        $token = Str::random(64);

        DB::table('company_password_reset_tokens')->updateOrInsert(
            ['company_id' => $companyId, 'email' => $email],
            [
                'token' => hash('sha256', $token),
                'created_at' => now(),
            ],
        );

        $user->notify(new ResetPassword($token));

        return Password::RESET_LINK_SENT;
    }

    public function reset(array $data, int $companyId): string
    {
        $user = User::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('email', $data['email'])
            ->first();

        if (! $user) {
            return Password::INVALID_USER;
        }

        $record = DB::table('company_password_reset_tokens')
            ->where('company_id', $companyId)
            ->where('email', $data['email'])
            ->first();

        if (! $record || ! $record->created_at || now()->diffInMinutes($record->created_at) > 60) {
            return Password::INVALID_TOKEN;
        }

        if (! hash_equals($record->token, hash('sha256', (string) $data['token']))) {
            return Password::INVALID_TOKEN;
        }

        DB::transaction(function () use ($user, $data, $companyId) {
            $user->forceFill(['password' => Hash::make((string) $data['password'])])->save();
            $user->tokens()->delete();

            DB::table('company_password_reset_tokens')
                ->where('company_id', $companyId)
                ->where('email', $data['email'])
                ->delete();
        });

        return Password::PASSWORD_RESET;
    }
}
