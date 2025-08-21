<?php

namespace App\__Domain__\Data\Prospect;

/**
 * Entité de domaine Prospect
 */
class Model
{
    public ?int $id;
    public int $userId;
    public string $name;
    public ?string $company;
    public ?string $sector;
    public ?string $city;
    public ?string $postalCode;
    public ?string $address;
    public ?array $contactInfo; // emails, phones, website, socialNetworks
    public ?string $description;
    public int $relevanceScore;
    public string $status;
    public ?string $source;
    public ?string $externalId;
    public ?array $rawData;
    public ?\DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        int $userId,
        string $name,
        ?string $company = null,
        ?string $sector = null,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $address = null,
        ?array $contactInfo = null,
        ?string $description = null,
        int $relevanceScore = 0,
        string $status = 'new',
        ?string $source = null,
        ?string $externalId = null,
        ?array $rawData = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->company = $company;
        $this->sector = $sector;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->address = $address;
        $this->contactInfo = $contactInfo ?? [];
        $this->description = $description;
        $this->relevanceScore = $relevanceScore;
        $this->status = $status;
        $this->source = $source;
        $this->externalId = $externalId;
        $this->rawData = $rawData ?? [];
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getEmail(): ?string
    {
        return $this->contactInfo['email'] ?? null;
    }

    public function getPhone(): ?string
    {
        return $this->contactInfo['phone'] ?? null;
    }

    public function getWebsite(): ?string
    {
        return $this->contactInfo['website'] ?? null;
    }

    public function updateStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function updateRelevanceScore(int $score): self
    {
        $this->relevanceScore = max(0, min(100, $score));
        return $this;
    }
}