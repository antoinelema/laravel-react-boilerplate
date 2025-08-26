<?php

namespace App\__Domain__\Data\ProspectCategory;

/**
 * Factory pour créer des instances de ProspectCategory
 */
class Factory
{
    /**
     * Créé une nouvelle instance de ProspectCategory depuis les données de base
     */
    public static function createFromData(array $data, int $userId): Model
    {
        return new Model(
            id: $data['id'] ?? null,
            userId: $userId,
            name: $data['name'],
            color: $data['color'] ?? '#3b82f6',
            position: $data['position'] ?? 0,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null
        );
    }

    /**
     * Créé une nouvelle instance depuis un modèle Eloquent
     */
    public static function createFromEloquent(\Illuminate\Database\Eloquent\Model $eloquent): Model
    {
        return new Model(
            id: $eloquent->id,
            userId: $eloquent->user_id,
            name: $eloquent->name,
            color: $eloquent->color,
            position: $eloquent->position,
            createdAt: $eloquent->created_at ? new \DateTimeImmutable($eloquent->created_at) : null,
            updatedAt: $eloquent->updated_at ? new \DateTimeImmutable($eloquent->updated_at) : null
        );
    }

    /**
     * Créé les catégories par défaut pour un utilisateur
     */
    public static function createDefaultCategories(int $userId): array
    {
        $defaultCategories = [
            [
                'name' => 'Nouveaux prospects',
                'color' => '#3b82f6',
                'position' => 1
            ],
            [
                'name' => 'En cours',
                'color' => '#f59e0b',
                'position' => 2
            ],
            [
                'name' => 'Qualifiés',
                'color' => '#10b981',
                'position' => 3
            ],
            [
                'name' => 'Convertis',
                'color' => '#059669',
                'position' => 4
            ]
        ];

        return array_map(function($categoryData) use ($userId) {
            return self::createFromData($categoryData, $userId);
        }, $defaultCategories);
    }

    /**
     * Valide les données d'entrée pour la création
     */
    public static function validateData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Le nom de la catégorie est requis';
        }

        if (strlen($data['name'] ?? '') > 255) {
            $errors[] = 'Le nom de la catégorie ne peut pas dépasser 255 caractères';
        }

        if (isset($data['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            $errors[] = 'La couleur doit être au format hexadécimal (#rrggbb)';
        }

        if (isset($data['position']) && (!is_numeric($data['position']) || $data['position'] < 0)) {
            $errors[] = 'La position doit être un nombre positif';
        }

        return $errors;
    }
}