<?php

namespace App\__Infrastructure__\Persistence\Prospect;

use App\__Domain__\Data\Prospect\Collection;
use App\__Domain__\Data\Prospect\Model;
use App\__Infrastructure__\Eloquent\ProspectEloquent;

class ProspectRepository implements Collection
{
    public function findById(int $id): ?Model
    {
        $eloquent = ProspectEloquent::find($id);
        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function findByUserId(int $userId): array
    {
        $prospects = ProspectEloquent::where('user_id', $userId)
                                   ->orderBy('created_at', 'desc')
                                   ->get();

        return $prospects->map(fn($p) => $this->toDomain($p))->toArray();
    }

    public function findByUserIdWithFilters(int $userId, array $filters): array
    {
        $query = ProspectEloquent::where('user_id', $userId);

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['sector'])) {
            $query->bySector($filters['sector']);
        }

        if (!empty($filters['city'])) {
            $query->byCity($filters['city']);
        }

        if (!empty($filters['min_score'])) {
            $query->byRelevanceScore($filters['min_score']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        $prospects = $query->get();
        return $prospects->map(fn($p) => $this->toDomain($p))->toArray();
    }

    public function findByExternalId(string $externalId, string $source): ?Model
    {
        $eloquent = ProspectEloquent::where('external_id', $externalId)
                                  ->where('source', $source)
                                  ->first();

        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function save(Model $prospect): Model
    {
        if ($prospect->id) {
            // Update existing prospect
            $eloquent = ProspectEloquent::findOrFail($prospect->id);
            $eloquent->fill($this->toArray($prospect));
            $eloquent->save();
        } else {
            // Create new prospect
            $data = $this->toArray($prospect);
            unset($data['id']);
            $eloquent = ProspectEloquent::create($data);
        }

        return $this->toDomain($eloquent->fresh());
    }

    public function delete(Model $prospect): void
    {
        if ($prospect->id) {
            ProspectEloquent::destroy($prospect->id);
        }
    }

    public function countByUserId(int $userId): int
    {
        return ProspectEloquent::where('user_id', $userId)->count();
    }

    public function searchByQuery(int $userId, string $query): array
    {
        $prospects = ProspectEloquent::where('user_id', $userId)
                                   ->search($query)
                                   ->orderBy('relevance_score', 'desc')
                                   ->get();

        return $prospects->map(fn($p) => $this->toDomain($p))->toArray();
    }

    private function toDomain(ProspectEloquent $eloquent): Model
    {
        return new Model(
            id: $eloquent->id,
            userId: $eloquent->user_id,
            name: $eloquent->name,
            company: $eloquent->company,
            sector: $eloquent->sector,
            city: $eloquent->city,
            postalCode: $eloquent->postal_code,
            address: $eloquent->address,
            contactInfo: $eloquent->contact_info ?? [],
            description: $eloquent->description,
            relevanceScore: $eloquent->relevance_score,
            status: $eloquent->status,
            source: $eloquent->source,
            externalId: $eloquent->external_id,
            rawData: $eloquent->raw_data ?? [],
            createdAt: $eloquent->created_at ? new \DateTimeImmutable($eloquent->created_at) : null,
            updatedAt: $eloquent->updated_at ? new \DateTimeImmutable($eloquent->updated_at) : null
        );
    }

    private function toArray(Model $prospect): array
    {
        return [
            'id' => $prospect->id,
            'user_id' => $prospect->userId,
            'name' => $prospect->name,
            'company' => $prospect->company,
            'sector' => $prospect->sector,
            'city' => $prospect->city,
            'postal_code' => $prospect->postalCode,
            'address' => $prospect->address,
            'contact_info' => $prospect->contactInfo,
            'description' => $prospect->description,
            'relevance_score' => $prospect->relevanceScore,
            'status' => $prospect->status,
            'source' => $prospect->source,
            'external_id' => $prospect->externalId,
            'raw_data' => $prospect->rawData,
        ];
    }
}