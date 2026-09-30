<?php

namespace App\Exceptions;

class PrerequisiteConflictException extends ApiException
{
    /**
     * @param  array<int, array{lesson_id: int, lesson_name: string, required_lesson_id: int, required_lesson_name: string}>  $conflicts
     */
    public function __construct(array $conflicts, string $message = 'A reordenação remove travas de pré-requisito.')
    {
        parent::__construct($message, 409, 'prerequisite_conflict', ['conflicts' => $conflicts]);
    }
}
