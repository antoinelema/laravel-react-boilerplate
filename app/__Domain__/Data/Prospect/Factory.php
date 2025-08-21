<?php

namespace App\__Domain__\Data\Prospect;

/**
 * Factory pour créer des instances de Prospect
 */
class Factory
{
    public static function create(array $data): Model
    {
        return new Model(
            id: $data['id'] ?? null,
            userId: $data['user_id'],
            name: $data['name'],
            company: $data['company'] ?? null,
            sector: $data['sector'] ?? null,
            city: $data['city'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            address: $data['address'] ?? null,
            contactInfo: $data['contact_info'] ?? [],
            description: $data['description'] ?? null,
            relevanceScore: $data['relevance_score'] ?? 0,
            status: $data['status'] ?? 'new',
            source: $data['source'] ?? null,
            externalId: $data['external_id'] ?? null,
            rawData: $data['raw_data'] ?? [],
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null
        );
    }

    public static function createFromApiData(array $apiData, int $userId, string $source): Model
    {
        $contactInfo = self::extractContactInfo($apiData);
        
        return new Model(
            id: null,
            userId: $userId,
            name: $apiData['name'] ?? 'Unknown',
            company: $apiData['company'] ?? null,
            sector: $apiData['sector'] ?? $apiData['category'] ?? null,
            city: $apiData['city'] ?? $apiData['address']['city'] ?? null,
            postalCode: $apiData['postal_code'] ?? $apiData['address']['postal_code'] ?? null,
            address: $apiData['address']['full'] ?? $apiData['address'] ?? null,
            contactInfo: $contactInfo,
            description: $apiData['description'] ?? null,
            relevanceScore: self::calculateRelevanceScore($apiData),
            status: 'new',
            source: $source,
            externalId: $apiData['id'] ?? $apiData['external_id'] ?? null,
            rawData: $apiData
        );
    }

    private static function extractContactInfo(array $apiData): array
    {
        $contactInfo = [];

        if (!empty($apiData['email'])) {
            $contactInfo['email'] = $apiData['email'];
        }

        if (!empty($apiData['phone'])) {
            $contactInfo['phone'] = $apiData['phone'];
        }

        if (!empty($apiData['website'])) {
            $contactInfo['website'] = $apiData['website'];
        }

        if (!empty($apiData['social_networks'])) {
            $contactInfo['social_networks'] = $apiData['social_networks'];
        }

        return $contactInfo;
    }

    private static function calculateRelevanceScore(array $apiData): int
    {
        $score = 50; // Base score

        // Bonus for having contact information
        if (!empty($apiData['email'])) $score += 20;
        if (!empty($apiData['phone'])) $score += 15;
        if (!empty($apiData['website'])) $score += 10;

        // Bonus for having detailed information
        if (!empty($apiData['description'])) $score += 5;
        if (!empty($apiData['company'])) $score += 5;

        return min(100, $score);
    }
}