<?php

namespace App\Exceptions;

class BusinessRuleException extends ApiException
{
    public function __construct(string $message = 'Não foi possível concluir a operação.', ?string $code = 'business_rule')
    {
        parent::__construct($message, 422, $code);
    }
}
