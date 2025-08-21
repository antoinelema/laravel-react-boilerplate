<?php

namespace App\__Domain__\Data\ProspectNote;

/**
 * Factory pour créer des instances de ProspectNote
 */
class Factory
{
    public static function create(array $data): Model
    {
        return new Model(
            id: $data['id'] ?? null,
            prospectId: $data['prospect_id'],
            userId: $data['user_id'],
            content: $data['content'],
            type: $data['type'] ?? 'note',
            remindedAt: isset($data['reminded_at']) ? new \DateTimeImmutable($data['reminded_at']) : null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null
        );
    }

    public static function createNote(int $prospectId, int $userId, string $content): Model
    {
        return new Model(
            id: null,
            prospectId: $prospectId,
            userId: $userId,
            content: $content,
            type: 'note'
        );
    }

    public static function createInteraction(int $prospectId, int $userId, string $content): Model
    {
        return new Model(
            id: null,
            prospectId: $prospectId,
            userId: $userId,
            content: $content,
            type: 'interaction'
        );
    }

    public static function createReminder(
        int $prospectId, 
        int $userId, 
        string $content, 
        \DateTimeImmutable $remindAt
    ): Model {
        return new Model(
            id: null,
            prospectId: $prospectId,
            userId: $userId,
            content: $content,
            type: 'reminder',
            remindedAt: $remindAt
        );
    }
}