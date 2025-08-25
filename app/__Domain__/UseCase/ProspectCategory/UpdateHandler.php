<?php

namespace App\__Domain__\UseCase\ProspectCategory;

use App\__Domain__\UseCase\ProspectCategory\Input\UpdateInput;
use App\__Domain__\UseCase\ProspectCategory\Output\UpdateOutput;
use App\__Domain__\Data\ProspectCategory\Factory as ProspectCategoryFactory;
use App\__Infrastructure__\Persistence\ProspectCategory\ProspectCategoryRepository;
use Illuminate\Support\Facades\Log;

class UpdateHandler
{
    public function __construct(
        private ProspectCategoryRepository $repository
    ) {}

    public function handle(UpdateInput $input): UpdateOutput
    {
        try {
            // Récupérer la catégorie existante
            $category = $this->repository->findByIdAndUser($input->categoryId, $input->userId);
            if (!$category) {
                return new UpdateOutput(
                    success: false,
                    errorMessage: 'Catégorie introuvable'
                );
            }

            // Vérifier le nom unique si modifié
            if ($input->name && $input->name !== $category->name) {
                $existingCategory = $this->repository->findByNameAndUser($input->name, $input->userId);
                if ($existingCategory) {
                    return new UpdateOutput(
                        success: false,
                        errorMessage: 'Une catégorie avec ce nom existe déjà'
                    );
                }
            }

            // Appliquer les modifications
            if ($input->name) {
                $category->updateName($input->name);
            }
            if ($input->color) {
                $category->updateColor($input->color);
            }
            if ($input->position !== null) {
                $category->updatePosition($input->position);
            }

            // Sauvegarder
            $updatedCategory = $this->repository->save($category);

            return new UpdateOutput(
                category: $updatedCategory,
                success: true
            );

        } catch (\Exception $e) {
            Log::error('Error updating prospect category', [
                'user_id' => $input->userId,
                'category_id' => $input->categoryId,
                'error' => $e->getMessage()
            ]);

            return new UpdateOutput(
                success: false,
                errorMessage: 'Erreur lors de la mise à jour de la catégorie'
            );
        }
    }
}