<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $mailTitle,
        public readonly string $mailBody,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
        public readonly ?string $code = null,
        public readonly ?string $companyName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.transactional',
            with: [
                'mailTitle' => $this->mailTitle,
                'mailBody' => $this->mailBody,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'code' => $this->code,
                'companyName' => $this->companyName,
            ],
        );
    }
}
