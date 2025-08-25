<?php

namespace App\__Domain__\UseCase\ProspectCategory;

use App\__Domain__\UseCase\ProspectCategory\Input\CreateInput;
use App\__Domain__\UseCase\ProspectCategory\Output\CreateOutput;
use App\__Domain__\Data\ProspectCategory\Factory as ProspectCategoryFactory;
use App\__Infrastructure__\Persistence\ProspectCategory\ProspectCategoryRepository;
use Illuminate\Support\Facades\Log;

class CreateHandler
{
    public function __construct(
        private ProspectCategoryRepository $repository
    ) {}

    public function handle(CreateInput $input): CreateOutput
    {
        try {
            // Vérifier si une catégorie avec ce nom existe déjà
            $existingCategory = $this->repository->findByNameAndUser($input->name, $input->userId);
            if ($existingCategory) {
                return new CreateOutput(
                    success: false,
                    errorMessage: 'Une catégorie avec ce nom existe déjà'
                );
            }

            // Valider les données
            $validationErrors = ProspectCategoryFactory::validateData([
                'name' => $input->name,
                'color' => $input->color,
                'position' => $input->position
            ]);

            if (!empty($validationErrors)) {
                return new CreateOutput(
                    success: false,
                    errorMessage: implode(', ', $validationErrors)
                );
            }

            // Déterminer la position si non spécifiée
            $position = $input->position ?? $this->repository->getNextPosition($input->userId);

            // Créer la catégorie
            $categoryData = [
                'name' => $input->name,
                'color' => $input->color,
                'position' => $position
            ];

            $category = ProspectCategoryFactory::createFromData($categoryData, $input->userId);
            $savedCategory = $this->repository->save($category);

            return new CreateOutput(
                category: $savedCategory,
                success: true
            );

        } catch (\Exception $e) {
            Log::error('Error creating prospect category', [
                'user_id' => $input->userId,
                'name' => $input->name,
                'error' => $e->getMessage()
            ]);

            return new CreateOutput(
                success: false,
                errorMessage: 'Erreur lors de la création de la catégorie'
            );
        }
    }
}