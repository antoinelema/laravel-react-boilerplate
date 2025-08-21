<?php

namespace App\__Domain__\UseCase\Prospect\Search;

/**
 * Input pour le Use Case de recherche de prospects
 */
class Input
{
    public int $userId;
    public string $query;
    public array $filters;
    public array $sources;
    public bool $saveSearch;

    public function __construct(
        int $userId,
        string $query,
        array $filters = [],
        array $sources = [],
        bool $saveSearch = true
    ) {
        $this->userId = $userId;
        $this->query = $query;
        $this->filters = $filters;
        $this->sources = $sources;
        $this->saveSearch = $saveSearch;
    }
}