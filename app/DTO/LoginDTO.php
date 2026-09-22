<?php

namespace App\DTO;

class LoginDTO
{
    public function __construct(
        public readonly string $login,
        public readonly string $password,
        public readonly string $deviceName = 'api',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            login: $data['email'] ?? $data['username'] ?? $data['login'],
            password: $data['password'],
            deviceName: $data['device_name'] ?? 'api',
        );
    }
}
