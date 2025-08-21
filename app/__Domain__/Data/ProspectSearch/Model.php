<?php

namespace App\__Domain__\Data\ProspectSearch;

/**
 * Entité de domaine ProspectSearch
 */
class Model
{
    public ?int $id;
    public int $userId;
    public string $query;
    public array $filters;
    public array $sources;
    public int $resultsCount;
    public int $savedCount;
    public ?\DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        int $userId,
        string $query,
        array $filters = [],
        array $sources = [],
        int $resultsCount = 0,
        int $savedCount = 0,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->query = $query;
        $this->filters = $filters;
        $this->sources = $sources;
        $this->resultsCount = $resultsCount;
        $this->savedCount = $savedCount;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function updateResults(int $resultsCount): self
    {
        $this->resultsCount = $resultsCount;
        return $this;
    }

    public function incrementSavedCount(): self
    {
        $this->savedCount++;
        return $this;
    }

    public function getConversionRate(): float
    {
        if ($this->resultsCount === 0) {
            return 0.0;
        }
        
        return ($this->savedCount / $this->resultsCount) * 100;
    }

    public function hasFilter(string $filterKey): bool
    {
        return isset($this->filters[$filterKey]);
    }

    public function getFilter(string $filterKey): mixed
    {
        return $this->filters[$filterKey] ?? null;
    }
}