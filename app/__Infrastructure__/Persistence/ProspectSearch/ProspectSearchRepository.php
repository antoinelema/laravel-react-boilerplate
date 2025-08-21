<?php

namespace App\__Infrastructure__\Persistence\ProspectSearch;

use App\__Domain__\Data\ProspectSearch\Collection;
use App\__Domain__\Data\ProspectSearch\Model;
use App\__Infrastructure__\Eloquent\ProspectSearchEloquent;

class ProspectSearchRepository implements Collection
{
    public function findById(int $id): ?Model
    {
        $eloquent = ProspectSearchEloquent::find($id);
        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function findByUserId(int $userId): array
    {
        $searches = ProspectSearchEloquent::where('user_id', $userId)
                                        ->orderBy('created_at', 'desc')
                                        ->get();

        return $searches->map(fn($s) => $this->toDomain($s))->toArray();
    }

    public function findRecentByUserId(int $userId, int $limit = 10): array
    {
        $searches = ProspectSearchEloquent::where('user_id', $userId)
                                        ->recent($limit)
                                        ->get();

        return $searches->map(fn($s) => $this->toDomain($s))->toArray();
    }

    public function findPopularQueriesByUserId(int $userId, int $limit = 5): array
    {
        $searches = ProspectSearchEloquent::where('user_id', $userId)
                                        ->popular($limit)
                                        ->get();

        return $searches->map(fn($s) => $this->toDomain($s))->toArray();
    }

    public function save(Model $search): Model
    {
        if ($search->id) {
            // Update existing search
            $eloquent = ProspectSearchEloquent::findOrFail($search->id);
            $eloquent->fill($this->toArray($search));
            $eloquent->save();
        } else {
            // Create new search
            $data = $this->toArray($search);
            unset($data['id']);
            $eloquent = ProspectSearchEloquent::create($data);
        }

        return $this->toDomain($eloquent->fresh());
    }

    public function delete(Model $search): void
    {
        if ($search->id) {
            ProspectSearchEloquent::destroy($search->id);
        }
    }

    public function deleteOldSearches(int $userId, int $daysToKeep = 30): int
    {
        return ProspectSearchEloquent::where('user_id', $userId)
                                   ->olderThan($daysToKeep)
                                   ->delete();
    }

    private function toDomain(ProspectSearchEloquent $eloquent): Model
    {
        return new Model(
            id: $eloquent->id,
            userId: $eloquent->user_id,
            query: $eloquent->query,
            filters: $eloquent->filters ?? [],
            sources: $eloquent->sources ?? [],
            resultsCount: $eloquent->results_count,
            savedCount: $eloquent->saved_count,
            createdAt: $eloquent->created_at ? new \DateTimeImmutable($eloquent->created_at) : null,
            updatedAt: $eloquent->updated_at ? new \DateTimeImmutable($eloquent->updated_at) : null
        );
    }

    private function toArray(Model $search): array
    {
        return [
            'id' => $search->id,
            'user_id' => $search->userId,
            'query' => $search->query,
            'filters' => $search->filters,
            'sources' => $search->sources,
            'results_count' => $search->resultsCount,
            'saved_count' => $search->savedCount,
        ];
    }
}