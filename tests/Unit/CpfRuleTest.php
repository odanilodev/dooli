<?php

namespace Tests\Unit;

use App\Rules\Cpf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CpfRuleTest extends TestCase
{
    #[DataProvider('validCpfs')]
    public function test_accepts_valid_cpfs(string $cpf): void
    {
        $this->assertTrue(Cpf::isValid($cpf));
    }

    #[DataProvider('invalidCpfs')]
    public function test_rejects_invalid_cpfs(?string $cpf): void
    {
        $this->assertFalse(Cpf::isValid($cpf));
    }

    public static function validCpfs(): array
    {
        return [['529.982.247-25'], ['52998224725'], ['111.444.777-35']];
    }

    public static function invalidCpfs(): array
    {
        return [
            ['123.456.789-00'],
            ['529.982.247-24'],
            ['111.111.111-11'],
            ['000.000.000-00'],
            ['1234'],
            [''],
            [null],
        ];
    }
}
