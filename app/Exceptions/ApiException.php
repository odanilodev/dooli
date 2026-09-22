<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    public function __construct(
        string $message = '',
        protected int $statusCode = 400,
        protected ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], fn ($value) => $value !== null), $this->statusCode);
    }
}
