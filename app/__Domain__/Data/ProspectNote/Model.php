<?php

namespace App\__Domain__\Data\ProspectNote;

/**
 * Entité de domaine ProspectNote
 */
class Model
{
    public ?int $id;
    public int $prospectId;
    public int $userId;
    public string $content;
    public string $type;
    public ?\DateTimeImmutable $remindedAt;
    public ?\DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        int $prospectId,
        int $userId,
        string $content,
        string $type = 'note',
        ?\DateTimeImmutable $remindedAt = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->prospectId = $prospectId;
        $this->userId = $userId;
        $this->content = $content;
        $this->type = $type;
        $this->remindedAt = $remindedAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function isReminder(): bool
    {
        return $this->type === 'reminder';
    }

    public function isInteraction(): bool
    {
        return $this->type === 'interaction';
    }

    public function updateContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setReminder(\DateTimeImmutable $remindAt): self
    {
        $this->type = 'reminder';
        $this->remindedAt = $remindAt;
        return $this;
    }
}