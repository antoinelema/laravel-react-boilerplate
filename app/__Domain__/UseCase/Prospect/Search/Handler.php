<?php

namespace App\__Domain__\UseCase\Prospect\Search;

use App\__Domain__\Data\ProspectSearch\Collection as ProspectSearchCollection;
use App\__Domain__\Data\ProspectSearch\Factory as ProspectSearchFactory;
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;

/**
 * Handler pour la recherche de prospects
 */
class Handler
{
    private ProspectEnrichmentService $enrichmentService;
    private ProspectSearchCollection $searchCollection;

    public function __construct(
        ProspectEnrichmentService $enrichmentService,
        ProspectSearchCollection $searchCollection
    ) {
        $this->enrichmentService = $enrichmentService;
        $this->searchCollection = $searchCollection;
    }

    public function handle(Input $input): Output
    {
        try {
            // Validation des données d'entrée
            if (empty(trim($input->query))) {
                return Output::failure('Le terme de recherche est requis');
            }

            // Recherche des prospects via les services externes
            $prospects = $this->enrichmentService->searchProspects(
                $input->userId,
                $input->query,
                $input->filters,
                $input->sources
            );

            // Sauvegarde de la recherche si demandée
            $search = null;
            if ($input->saveSearch) {
                $search = $this->saveSearch($input, count($prospects));
            }

            // Obtention des sources disponibles pour information
            $availableSources = $this->enrichmentService->getAvailableSources();

            return Output::success($prospects, $search, $availableSources);

        } catch (\Exception $e) {
            return Output::failure('Erreur lors de la recherche: ' . $e->getMessage());
        }
    }

    private function saveSearch(Input $input, int $resultsCount): ?\App\__Domain__\Data\ProspectSearch\Model
    {
        try {
            $search = ProspectSearchFactory::createWithResults(
                $input->userId,
                $input->query,
                $input->filters,
                $input->sources,
                $resultsCount
            );

            return $this->searchCollection->save($search);

        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas échouer la recherche
            \Illuminate\Support\Facades\Log::warning('Failed to save search history', [
                'user_id' => $input->userId,
                'query' => $input->query,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}