<?php

namespace App\__Infrastructure__\Persistence\ProspectCategory;

use App\__Domain__\Data\ProspectCategory\Model as ProspectCategoryModel;
use App\__Domain__\Data\ProspectCategory\Factory as ProspectCategoryFactory;
use App\__Infrastructure__\Eloquent\ProspectCategoryEloquent;
use Illuminate\Support\Collection;

class ProspectCategoryRepository
{
    public function __construct(
        private ProspectCategoryEloquent $eloquent
    ) {}

    /**
     * Trouve toutes les catégories d'un utilisateur
     */
    public function findAllByUser(int $userId): Collection
    {
        $eloquents = $this->eloquent
            ->forUser($userId)
            ->orderedByPosition()
            ->get();

        return $eloquents->map(function ($eloquent) {
            return ProspectCategoryFactory::createFromEloquent($eloquent);
        });
    }

    /**
     * Trouve une catégorie par ID et utilisateur
     */
    public function findByIdAndUser(int $id, int $userId): ?ProspectCategoryModel
    {
        $eloquent = $this->eloquent
            ->forUser($userId)
            ->where('id', $id)
            ->first();

        return $eloquent ? ProspectCategoryFactory::createFromEloquent($eloquent) : null;
    }

    /**
     * Sauvegarde une catégorie
     */
    public function save(ProspectCategoryModel $category): ProspectCategoryModel
    {
        if ($category->id) {
            // Mise à jour
            $eloquent = $this->eloquent->find($category->id);
            if (!$eloquent) {
                throw new \InvalidArgumentException("Category not found: {$category->id}");
            }
        } else {
            // Création
            $eloquent = new ProspectCategoryEloquent();
        }

        $eloquent->user_id = $category->userId;
        $eloquent->name = $category->name;
        $eloquent->color = $category->color;
        $eloquent->position = $category->position;
        $eloquent->save();

        return ProspectCategoryFactory::createFromEloquent($eloquent);
    }

    /**
     * Supprime une catégorie
     */
    public function delete(int $id, int $userId): bool
    {
        $deleted = $this->eloquent
            ->forUser($userId)
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Trouve une catégorie par nom et utilisateur
     */
    public function findByNameAndUser(string $name, int $userId): ?ProspectCategoryModel
    {
        $eloquent = $this->eloquent
            ->forUser($userId)
            ->where('name', $name)
            ->first();

        return $eloquent ? ProspectCategoryFactory::createFromEloquent($eloquent) : null;
    }

    /**
     * Compte le nombre de prospects dans une catégorie
     */
    public function countProspects(int $categoryId): int
    {
        $eloquent = $this->eloquent->find($categoryId);
        return $eloquent ? $eloquent->prospects()->count() : 0;
    }

    /**
     * Obtient les catégories avec le nombre de prospects
     */
    public function findAllByUserWithProspectCounts(int $userId): Collection
    {
        $eloquents = $this->eloquent
            ->forUser($userId)
            ->orderedByPosition()
            ->withCount('prospects')
            ->get();

        return $eloquents->map(function ($eloquent) {
            $category = ProspectCategoryFactory::createFromEloquent($eloquent);
            // On ajoute le count comme propriété supplémentaire
            $category->prospectsCount = $eloquent->prospects_count;
            return $category;
        });
    }

    /**
     * Met à jour les positions de plusieurs catégories
     */
    public function updatePositions(array $categoryPositions, int $userId): void
    {
        foreach ($categoryPositions as $categoryId => $position) {
            $this->eloquent
                ->forUser($userId)
                ->where('id', $categoryId)
                ->update(['position' => $position]);
        }
    }

    /**
     * Obtient la prochaine position disponible pour un utilisateur
     */
    public function getNextPosition(int $userId): int
    {
        $maxPosition = $this->eloquent
            ->forUser($userId)
            ->max('position');

        return ($maxPosition ?? 0) + 1;
    }
}