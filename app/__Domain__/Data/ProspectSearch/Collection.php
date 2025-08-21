<?php

namespace App\__Domain__\Data\ProspectSearch;

/**
 * Interface Collection pour les ProspectSearches
 */
interface Collection
{
    public function findById(int $id): ?Model;
    
    public function findByUserId(int $userId): array;
    
    public function findRecentByUserId(int $userId, int $limit = 10): array;
    
    public function findPopularQueriesByUserId(int $userId, int $limit = 5): array;
    
    public function save(Model $search): Model;
    
    public function delete(Model $search): void;
    
    public function deleteOldSearches(int $userId, int $daysToKeep = 30): int;
}