<?php

namespace App\__Domain__\Data\Prospect;

/**
 * Interface Collection pour les Prospects
 */
interface Collection
{
    public function findById(int $id): ?Model;
    
    public function findByUserId(int $userId): array;
    
    public function findByUserIdWithFilters(int $userId, array $filters): array;
    
    public function findByExternalId(string $externalId, string $source): ?Model;
    
    public function save(Model $prospect): Model;
    
    public function delete(Model $prospect): void;
    
    public function countByUserId(int $userId): int;
    
    public function searchByQuery(int $userId, string $query): array;
}