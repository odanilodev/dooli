<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF válido: 11 dígitos, não repetidos (111.111.111-11 etc.) e com os dois dígitos verificadores corretos.
 */
class Cpf implements ValidationRule
{
    public static function isValid(?string $value): bool
    {
        $cpf = preg_replace('/\D/', '', (string) $value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($length = 9; $length <= 10; $length++) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * ($length + 1 - $i);
            }

            $digit = ($sum * 10) % 11 % 10;

            if ((int) $cpf[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid(is_scalar($value) ? (string) $value : null)) {
            $fail('Informe um CPF válido.');
        }
    }
}
