<?php

namespace App\__Domain__\Data\ProspectSearch;

/**
 * Factory pour créer des instances de ProspectSearch
 */
class Factory
{
    public static function create(array $data): Model
    {
        return new Model(
            id: $data['id'] ?? null,
            userId: $data['user_id'],
            query: $data['query'],
            filters: $data['filters'] ?? [],
            sources: $data['sources'] ?? [],
            resultsCount: $data['results_count'] ?? 0,
            savedCount: $data['saved_count'] ?? 0,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null
        );
    }

    public static function createFromSearchRequest(
        int $userId,
        string $query,
        array $filters = [],
        array $sources = []
    ): Model {
        return new Model(
            id: null,
            userId: $userId,
            query: $query,
            filters: $filters,
            sources: $sources
        );
    }

    public static function createWithResults(
        int $userId,
        string $query,
        array $filters,
        array $sources,
        int $resultsCount
    ): Model {
        return new Model(
            id: null,
            userId: $userId,
            query: $query,
            filters: $filters,
            sources: $sources,
            resultsCount: $resultsCount
        );
    }
}