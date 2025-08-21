<?php

namespace App\__Application__\Http\Controllers\Api;

use App\__Application__\Http\Requests\ProspectSearchRequest;
use App\__Domain__\UseCase\Prospect\Search\Handler as SearchHandler;
use App\__Domain__\UseCase\Prospect\Search\Input as SearchInput;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur API pour la recherche de prospects
 */
class ProspectSearchController extends Controller
{
    public function __construct()
    {
        //
    }

    private function getSearchHandler(): SearchHandler
    {
        return app(SearchHandler::class);
    }

    /**
     * Recherche des prospects via les APIs externes
     */
    public function search(ProspectSearchRequest $request): JsonResponse
    {
        $userId = Auth::id();
        
        $input = new SearchInput(
            userId: $userId,
            query: $request->getQuery(),
            filters: $request->getFilters(),
            sources: $request->getSources(),
            saveSearch: $request->shouldSaveSearch()
        );

        $output = $this->getSearchHandler()->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'prospects' => array_map([$this, 'formatProspect'], $output->prospects),
                'total_found' => $output->totalFound,
                'search' => $output->search ? $this->formatSearch($output->search) : null,
                'available_sources' => $output->availableSources,
            ],
        ]);
    }

    /**
     * Obtient les sources disponibles et leur statut
     */
    public function sources(): JsonResponse
    {
        // Cette méthode pourrait être déplacée dans un service dédié
        $availableSources = [
            'pages_jaunes' => [
                'name' => 'Pages Jaunes',
                'available' => !empty(config('services.pages_jaunes.api_key')),
                'description' => 'Annuaire professionnel français'
            ],
            'google_maps' => [
                'name' => 'Google Maps',
                'available' => !empty(config('services.google_maps.api_key')),
                'description' => 'Établissements référencés sur Google Maps'
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'sources' => $availableSources,
            ],
        ]);
    }

    private function formatProspect(\App\__Domain__\Data\Prospect\Model $prospect): array
    {
        return [
            'id' => $prospect->id,
            'name' => $prospect->name,
            'company' => $prospect->company,
            'sector' => $prospect->sector,
            'city' => $prospect->city,
            'postal_code' => $prospect->postalCode,
            'address' => $prospect->address,
            'contact_info' => $prospect->contactInfo,
            'description' => $prospect->description,
            'relevance_score' => $prospect->relevanceScore,
            'status' => $prospect->status,
            'source' => $prospect->source,
            'external_id' => $prospect->externalId,
            'created_at' => $prospect->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $prospect->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    private function formatSearch(\App\__Domain__\Data\ProspectSearch\Model $search): array
    {
        return [
            'id' => $search->id,
            'query' => $search->query,
            'filters' => $search->filters,
            'sources' => $search->sources,
            'results_count' => $search->resultsCount,
            'saved_count' => $search->savedCount,
            'conversion_rate' => $search->getConversionRate(),
            'created_at' => $search->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}