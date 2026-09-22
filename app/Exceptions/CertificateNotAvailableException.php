<?php

namespace App\Exceptions;

class CertificateNotAvailableException extends ApiException
{
    public function __construct(string $message = 'Certificado indisponível: critérios de conclusão não atendidos.')
    {
        parent::__construct($message, 422, 'certificate_not_available');
    }
}
