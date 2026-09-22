<?php

namespace App\Exceptions;

class CourseNotFoundException extends ApiException
{
    public function __construct(string $message = 'Curso não encontrado.')
    {
        parent::__construct($message, 404, 'course_not_found');
    }
}
