<?php

namespace App\DTO;

class UpdateCourseDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly ?string $status = null,
        public readonly ?int $sortOrder = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            image: $data['image'] ?? null,
            status: $data['status'] ?? null,
            sortOrder: isset($data['sort_order']) ? (int) $data['sort_order'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'status' => $this->status,
            'sort_order' => $this->sortOrder,
        ], fn ($value) => $value !== null);
    }
}
