<?php

namespace App\Exceptions;

class ResendException extends ApiException
{
    public static function invalidCredentials(): self
    {
        return new self(
            'A API key do Resend é inválida ou não possui permissão para gerenciar domínios.',
            422,
            'resend_invalid_credentials',
        );
    }

    public static function rateLimited(): self
    {
        return new self(
            'O limite de requisições do Resend foi atingido. Tente novamente em instantes.',
            429,
            'resend_rate_limited',
        );
    }

    public static function requestFailed(string $message = ''): self
    {
        return new self(
            $message !== '' ? $message : 'Não foi possível comunicar com o Resend.',
            422,
            'resend_request_failed',
        );
    }
}
