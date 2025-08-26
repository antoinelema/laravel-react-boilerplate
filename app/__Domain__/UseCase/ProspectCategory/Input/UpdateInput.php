<?php

namespace App\__Domain__\UseCase\ProspectCategory\Input;

class UpdateInput
{
    public function __construct(
        public int $userId,
        public int $categoryId,
        public ?string $name = null,
        public ?string $color = null,
        public ?int $position = null
    ) {}

    public static function fromData(int $userId, int $categoryId, array $data): self
    {
        return new self(
            userId: $userId,
            categoryId: $categoryId,
            name: $data['name'] ?? null,
            color: $data['color'] ?? null,
            position: $data['position'] ?? null
        );
    }
}