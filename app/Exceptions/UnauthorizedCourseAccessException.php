<?php

namespace App\Exceptions;

class UnauthorizedCourseAccessException extends ApiException
{
    public function __construct(string $message = 'Você não possui acesso a este curso.')
    {
        parent::__construct($message, 403, 'unauthorized_course_access');
    }
}
