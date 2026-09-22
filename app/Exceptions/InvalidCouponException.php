<?php

namespace App\Exceptions;

class InvalidCouponException extends ApiException
{
    public function __construct(string $message = 'Cupom inválido.', ?string $code = 'invalid_coupon')
    {
        parent::__construct($message, 422, $code);
    }
}
