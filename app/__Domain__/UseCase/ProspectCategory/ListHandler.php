<?php

namespace App\__Domain__\UseCase\ProspectCategory;

use App\__Domain__\UseCase\ProspectCategory\Input\ListInput;
use App\__Domain__\UseCase\ProspectCategory\Output\ListOutput;
use App\__Infrastructure__\Persistence\ProspectCategory\ProspectCategoryRepository;
use Illuminate\Support\Facades\Log;

class ListHandler
{
    public function __construct(
        private ProspectCategoryRepository $repository
    ) {}

    public function handle(ListInput $input): ListOutput
    {
        try {
            $categories = $this->repository->findAllByUserWithProspectCounts($input->userId);
            
            return new ListOutput(
                categories: $categories,
                success: true
            );

        } catch (\Exception $e) {
            Log::error('Error listing prospect categories', [
                'user_id' => $input->userId,
                'error' => $e->getMessage()
            ]);

            return new ListOutput(
                categories: collect(),
                success: false,
                errorMessage: 'Erreur lors de la récupération des catégories'
            );
        }
    }
}