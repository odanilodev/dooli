<?php

namespace App\DTO;

class CreateTeamDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $status = 'active',
        public readonly int $sortOrder = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            status: $data['status'] ?? 'active',
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'sort_order' => $this->sortOrder,
        ];
    }
}
