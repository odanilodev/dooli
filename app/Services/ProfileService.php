<?php

namespace App\Services;

use App\Enums\NotificationSlug;
use App\Mail\TransactionalMail;
use App\Models\User;
use App\Repositories\UserRepository;
use Throwable;

class ProfileService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DatalogService $audit,
        private readonly NotificationService $notifications,
        private readonly CompanyMailService $mail,
    ) {}

    public function update(User $user, array $data): User
    {
        $oldEmail = $user->email;
        $updated = $this->users->update($user, $data);

        $this->audit->log('profile', 'update', 'user', 'Perfil atualizado', $user->id);

        if ($oldEmail !== $updated->email) {
            $this->notifyEmailChanged($updated, (string) $oldEmail);
        }

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

        try {
            $this->notifications->sendToUser(
                $user->refresh(),
                NotificationSlug::AccountPasswordChanged,
                [
                    'message' => 'Sua senha foi alterada. Se não reconhece esta ação, entre em contato com o suporte.',
                    'action_url' => 'configuracoes',
                ],
                "account_password_changed:{$user->id}:".now()->timestamp,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function notifyEmailChanged(User $user, string $oldEmail): void
    {
        try {
            $this->notifications->sendToUser(
                $user,
                NotificationSlug::AccountEmailChanged,
                [
                    'message' => "Seu e-mail de acesso foi alterado para {$user->email}.",
                    'action_url' => 'perfil',
                ],
                "account_email_changed:{$user->id}:{$user->email}",
            );

            $company = $user->company;

            if ($company && filled($oldEmail)) {
                $this->mail->send(
                    $company,
                    $oldEmail,
                    new TransactionalMail(
                        mailSubject: 'Seu e-mail de acesso foi alterado - '.$company->name,
                        mailTitle: 'E-mail de acesso alterado',
                        mailBody: "O e-mail de acesso da sua conta foi alterado para {$user->email}. Se não reconhece esta ação, entre em contato com o suporte imediatamente.",
                        companyName: $company->name,
                    ),
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
