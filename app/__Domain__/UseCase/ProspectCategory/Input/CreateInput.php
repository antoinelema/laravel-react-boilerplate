<?php

namespace App\__Domain__\UseCase\ProspectCategory\Input;

class CreateInput
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $color = '#3b82f6',
        public ?int $position = null
    ) {}

    public static function fromData(int $userId, array $data): self
    {
        return new self(
            userId: $userId,
            name: $data['name'],
            color: $data['color'] ?? '#3b82f6',
            position: $data['position'] ?? null
        );
    }
}