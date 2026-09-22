<?php

namespace App\DTO;

class CreateCourseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly string $status = 'active',
        public readonly int $sortOrder = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            image: $data['image'] ?? null,
            status: $data['status'] ?? 'active',
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'status' => $this->status,
            'sort_order' => $this->sortOrder,
        ];
    }
}
