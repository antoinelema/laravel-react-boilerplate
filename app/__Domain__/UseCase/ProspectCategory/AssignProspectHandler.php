<?php

namespace App\__Domain__\UseCase\ProspectCategory;

use App\__Domain__\UseCase\ProspectCategory\Input\AssignProspectInput;
use App\__Domain__\UseCase\ProspectCategory\Output\AssignProspectOutput;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use App\__Infrastructure__\Persistence\ProspectCategory\ProspectCategoryRepository;
use Illuminate\Support\Facades\Log;

class AssignProspectHandler
{
    public function __construct(
        private ProspectCategoryRepository $categoryRepository
    ) {}

    public function handle(AssignProspectInput $input): AssignProspectOutput
    {
        try {
            // Vérifier que le prospect appartient à l'utilisateur
            $prospect = ProspectEloquent::where('id', $input->prospectId)
                                      ->where('user_id', $input->userId)
                                      ->first();
            
            if (!$prospect) {
                return new AssignProspectOutput(
                    success: false,
                    errorMessage: 'Prospect introuvable'
                );
            }

            // Vérifier que toutes les catégories appartiennent à l'utilisateur
            foreach ($input->categoryIds as $categoryId) {
                $category = $this->categoryRepository->findByIdAndUser($categoryId, $input->userId);
                if (!$category) {
                    return new AssignProspectOutput(
                        success: false,
                        errorMessage: "Catégorie {$categoryId} introuvable"
                    );
                }
            }

            // Synchroniser les catégories (remplace toutes les associations existantes)
            $prospect->categories()->sync($input->categoryIds);

            return new AssignProspectOutput(success: true);

        } catch (\Exception $e) {
            Log::error('Error assigning prospect to categories', [
                'user_id' => $input->userId,
                'prospect_id' => $input->prospectId,
                'category_ids' => $input->categoryIds,
                'error' => $e->getMessage()
            ]);

            return new AssignProspectOutput(
                success: false,
                errorMessage: 'Erreur lors de l\'assignation du prospect aux catégories'
            );
        }
    }
}