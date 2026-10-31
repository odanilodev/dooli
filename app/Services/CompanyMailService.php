<?php

namespace App\Services;

use App\Mail\TransactionalMail;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Models\Notification;
use App\Models\User;
use App\Support\CompanyUrlService;
use Illuminate\Support\Facades\Mail;

class CompanyMailService
{
    public function __construct(
        private readonly CompanyUrlService $urls,
    ) {}

    public function settingFor(Company|int $company): ?CompanyMailSetting
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return CompanyMailSetting::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->first();
    }

    public function mailerNameFor(Company $company): string
    {
        $setting = $this->settingFor($company);

        if (! $setting?->isReady()) {
            return (string) config('mail.default');
        }

        $apiKey = (string) $setting->apiKey();
        $name = 'company_'.$company->id.'_'.substr(hash('sha256', $apiKey), 0, 12);

        config([
            "mail.mailers.{$name}" => [
                'transport' => 'resend',
                'key' => $apiKey,
            ],
        ]);

        return $name;
    }

    public function send(Company $company, string $to, TransactionalMail $mail): mixed
    {
        $this->applyIdentity($company, $mail);

        return Mail::mailer($this->mailerNameFor($company))
            ->to($to)
            ->send($mail);
    }

    public function sendVerificationCode(
        User $user,
        string $code,
        int $expiresInMinutes,
    ): mixed {
        $company = $user->company;
        $companyName = $company?->name ?? (string) config('app.name');

        return $this->send(
            $company,
            (string) $user->email,
            new TransactionalMail(
                mailSubject: 'Seu código de confirmação - '.$companyName,
                mailTitle: 'Confirme seu e-mail',
                mailBody: "Olá, {$user->name}!\n\nUse o código abaixo para confirmar seu e-mail e ativar sua conta. Ele expira em {$expiresInMinutes} minutos.",
                code: $code,
                companyName: $companyName,
            ),
        );
    }

    public function sendPasswordReset(User $user, string $token): mixed
    {
        $company = $user->company;
        $companyName = $company?->name ?? (string) config('app.name');
        $url = $this->urls->passwordResetUrl($user, $token);

        return $this->send(
            $company,
            (string) $user->email,
            new TransactionalMail(
                mailSubject: 'Redefinição de senha - '.$companyName,
                mailTitle: 'Redefina sua senha',
                mailBody: "Olá, {$user->name}!\n\nRecebemos uma solicitação para redefinir sua senha. O link abaixo expira em 60 minutos.",
                actionUrl: $url,
                actionLabel: 'Criar nova senha',
                companyName: $companyName,
            ),
        );
    }

    public function sendNotification(Notification $notification, User $user): mixed
    {
        $company = $notification->company()->firstOrFail();
        $actionUrl = $notification->action_url;

        if (filled($actionUrl) && ! str_starts_with($actionUrl, 'http://') && ! str_starts_with($actionUrl, 'https://')) {
            $actionUrl = $company->frontendPath($actionUrl);
        }

        $title = $notification->title ?: ($notification->type?->name ?? 'Notificação');

        return $this->send(
            $company,
            (string) $user->email,
            new TransactionalMail(
                mailSubject: $title,
                mailTitle: $title,
                mailBody: $notification->message ?: 'Você tem uma nova notificação.',
                actionUrl: $actionUrl,
                actionLabel: 'Abrir',
                companyName: $company->name,
            ),
        );
    }

    private function applyIdentity(Company $company, TransactionalMail $mail): void
    {
        $setting = $this->settingFor($company);

        if ($setting?->isReady()) {
            $mail->from((string) $setting->from_address, $setting->from_name ?: $company->name);

            if (filled($setting->reply_to)) {
                $mail->replyTo((string) $setting->reply_to);
            }

            return;
        }

        $from = config('mail.from', []);
        $address = $from['address'] ?? null;

        if (filled($address)) {
            $mail->from((string) $address, $from['name'] ?? null);
        }
    }
}
