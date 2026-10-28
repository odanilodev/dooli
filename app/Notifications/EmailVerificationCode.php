<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $companyName,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu código de confirmação - '.$this->companyName)
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Use o código abaixo para confirmar seu e-mail e ativar sua conta:')
            ->line($this->code)
            ->line('Este código expira em '.$this->expiresInMinutes.' minutos.')
            ->line('Se você não solicitou este cadastro, ignore esta mensagem.')
            ->salutation('Equipe '.$this->companyName);
    }
}
