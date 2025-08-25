<?php

namespace App\__Domain__\Data\ProspectCategory;

/**
 * Entité de domaine ProspectCategory
 */
class Model
{
    public ?int $id;
    public int $userId;
    public string $name;
    public string $color;
    public int $position;
    public ?\DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        int $userId,
        string $name,
        string $color = '#3b82f6',
        int $position = 0,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->color = $color;
        $this->position = $position;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function updateName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function updateColor(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function updatePosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function isValidColor(): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $this->color);
    }
}