<?php

namespace App\Support;

use App\Models\User;

class CompanyUrlService
{
    public function passwordResetUrl(User $user, string $token): string
    {
        $email = urlencode($user->getEmailForPasswordReset());
        $company = $user->company;

        if ($company?->domain && ! $user->isSuperAdmin()) {
            return $company->frontendUrl()."/reset-password?token={$token}&email={$email}";
        }

        $slug = urlencode((string) ($company?->slug ?? ''));

        return rtrim((string) config('app.frontend_url'), '/')
            ."/#/reset-password?token={$token}&email={$email}&company={$slug}";
    }
}
