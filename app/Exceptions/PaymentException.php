<?php

namespace App\Exceptions;

class PaymentException extends ApiException
{
    public function __construct(string $message = 'Falha ao processar o pagamento.', ?string $code = 'payment_error')
    {
        parent::__construct($message, 422, $code);
    }
}
