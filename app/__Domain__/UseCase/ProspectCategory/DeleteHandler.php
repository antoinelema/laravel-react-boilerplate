<?php

namespace App\__Domain__\UseCase\ProspectCategory;

use App\__Domain__\UseCase\ProspectCategory\Input\DeleteInput;
use App\__Domain__\UseCase\ProspectCategory\Output\DeleteOutput;
use App\__Infrastructure__\Persistence\ProspectCategory\ProspectCategoryRepository;
use Illuminate\Support\Facades\Log;

class DeleteHandler
{
    public function __construct(
        private ProspectCategoryRepository $repository
    ) {}

    public function handle(DeleteInput $input): DeleteOutput
    {
        try {
            // Vérifier si la catégorie existe
            $category = $this->repository->findByIdAndUser($input->categoryId, $input->userId);
            if (!$category) {
                return new DeleteOutput(
                    success: false,
                    errorMessage: 'Catégorie introuvable'
                );
            }

            // Supprimer la catégorie (les relations pivots seront supprimées automatiquement)
            $deleted = $this->repository->delete($input->categoryId, $input->userId);

            if (!$deleted) {
                return new DeleteOutput(
                    success: false,
                    errorMessage: 'Erreur lors de la suppression'
                );
            }

            return new DeleteOutput(success: true);

        } catch (\Exception $e) {
            Log::error('Error deleting prospect category', [
                'user_id' => $input->userId,
                'category_id' => $input->categoryId,
                'error' => $e->getMessage()
            ]);

            return new DeleteOutput(
                success: false,
                errorMessage: 'Erreur lors de la suppression de la catégorie'
            );
        }
    }
}