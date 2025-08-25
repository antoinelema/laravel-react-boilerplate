<?php

namespace App\__Application__\Http\Controllers\Api;

use App\__Application__\Http\Requests\SaveProspectRequest;
use App\__Domain__\Data\Prospect\Collection as ProspectCollection;
use App\__Domain__\UseCase\Prospect\Save\Handler as SaveHandler;
use App\__Domain__\UseCase\Prospect\Save\Input as SaveInput;
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contrôleur API pour la gestion des prospects
 */
class ProspectController extends Controller
{
    public function __construct()
    {
        //
    }

    private function getProspectCollection(): ProspectCollection
    {
        return app(ProspectCollection::class);
    }

    private function getSaveHandler(): SaveHandler
    {
        return app(SaveHandler::class);
    }

    /**
     * Liste les prospects de l'utilisateur avec filtres optionnels
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        
        $filters = $this->getFiltersFromRequest($request);
        
        $prospects = $this->getProspectCollection()->findByUserIdWithFilters($userId, $filters);

        return response()->json([
            'success' => true,
            'data' => [
                'prospects' => array_map([$this, 'formatProspect'], $prospects),
                'total' => count($prospects),
                'filters_applied' => $filters,
            ],
        ]);
    }

    /**
     * Affiche les détails d'un prospect spécifique
     */
    public function show(int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'prospect' => $this->formatProspect($prospect),
            ],
        ]);
    }

    /**
     * Sauvegarde plusieurs prospects en lot
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $userId = Auth::id();
        
        // Validation des données d'entrée
        $validated = $request->validate([
            'prospects' => 'required|array|min:1|max:100', // Limite à 100 prospects
            'prospects.*.name' => 'required|string|max:255',
            'prospects.*.company' => 'nullable|string|max:255',
            'prospects.*.sector' => 'nullable|string|max:255',
            'prospects.*.city' => 'nullable|string|max:255',
            'prospects.*.postal_code' => 'nullable|string|max:20',
            'prospects.*.address' => 'nullable|string',
            'prospects.*.contact_info' => 'nullable|array',
            'prospects.*.phone' => 'nullable|string|max:50',
            'prospects.*.email' => 'nullable|string|email|max:255',
            'prospects.*.website' => 'nullable|string|max:255',
            'prospects.*.description' => 'nullable|string',
            'prospects.*.relevance_score' => 'nullable|numeric|min:0|max:100',
            'prospects.*.source' => 'nullable|string|max:100',
            'prospects.*.external_id' => 'nullable|string|max:255',
            'search_id' => 'nullable|integer',
        ]);

        $results = [
            'saved' => 0,
            'exists' => 0, 
            'errors' => 0,
            'details' => []
        ];

        try {
            // Use database transaction without explicit begin since tests might already have one active
            DB::transaction(function () use ($validated, &$results, $userId) {

            foreach ($validated['prospects'] as $index => $prospectData) {
                try {
                    // Normaliser les données de contact
                    if (!isset($prospectData['contact_info']) || !is_array($prospectData['contact_info'])) {
                        $prospectData['contact_info'] = [];
                    }
                    
                    // Ajouter phone, email, website dans contact_info s'ils existent
                    if (isset($prospectData['phone'])) {
                        $prospectData['contact_info']['phone'] = $prospectData['phone'];
                        unset($prospectData['phone']);
                    }
                    if (isset($prospectData['email'])) {
                        $prospectData['contact_info']['email'] = $prospectData['email'];
                        unset($prospectData['email']);
                    }
                    if (isset($prospectData['website'])) {
                        $prospectData['contact_info']['website'] = $prospectData['website'];
                        unset($prospectData['website']);
                    }

                    $input = SaveInput::fromData(
                        $userId,
                        $prospectData,
                        $validated['search_id'] ?? null,
                        null // note
                    );

                    $output = $this->getSaveHandler()->handle($input);

                    if ($output->success) {
                        if ($output->wasAlreadyExists) {
                            $results['exists']++;
                        } else {
                            $results['saved']++;
                        }
                        
                        $results['details'][] = [
                            'index' => $index,
                            'name' => $prospectData['name'],
                            'status' => $output->wasAlreadyExists ? 'exists' : 'saved',
                            'prospect_id' => $output->prospect->id
                        ];
                    } else {
                        $results['errors']++;
                        $results['details'][] = [
                            'index' => $index,
                            'name' => $prospectData['name'],
                            'status' => 'error',
                            'error' => $output->errorMessage
                        ];
                    }

                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['details'][] = [
                        'index' => $index,
                        'name' => $prospectData['name'] ?? 'Inconnu',
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
            });

            return response()->json([
                'success' => true,
                'message' => $this->getBulkSaveMessage($results),
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde en lot: ' . $e->getMessage(),
                'data' => $results
            ], 500);
        }
    }

    /**
     * Sauvegarde un nouveau prospect
     */
    public function store(SaveProspectRequest $request): JsonResponse
    {
        $userId = Auth::id();
        
        $prospectData = $request->getProspectData();
        $note = $prospectData['note'] ?? null;
        
        // Retirer la note des données du prospect car elle sera gérée séparément
        unset($prospectData['note']);
        
        $input = SaveInput::fromData(
            $userId,
            $prospectData,
            $request->getSearchId(),
            $note
        );

        $output = $this->getSaveHandler()->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage,
            ], 400);
        }

        $statusCode = $output->wasAlreadyExists ? 200 : 201;
        $message = $output->wasAlreadyExists ? 'Prospect déjà existant' : 'Prospect sauvegardé avec succès';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'prospect' => $this->formatProspect($output->prospect),
                'was_already_exists' => $output->wasAlreadyExists,
            ],
        ], $statusCode);
    }

    /**
     * Met à jour un prospect existant
     */
    public function update(SaveProspectRequest $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé',
            ], 404);
        }

        // Mise à jour des propriétés du prospect
        $data = $request->getProspectData();
        $data['id'] = $id;
        $data['user_id'] = $userId;

        $input = SaveInput::fromData($userId, $data);
        $output = $this->getSaveHandler()->handle($input);

        if (!$output->success) {
            return response()->json([
                'success' => false,
                'message' => $output->errorMessage,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Prospect mis à jour avec succès',
            'data' => [
                'prospect' => $this->formatProspect($output->prospect),
            ],
        ]);
    }

    /**
     * Supprime un prospect
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé',
            ], 404);
        }

        try {
            $this->getProspectCollection()->delete($prospect);

            return response()->json([
                'success' => true,
                'message' => 'Prospect supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recherche dans les prospects existants de l'utilisateur
     */
    public function search(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $query = $request->get('query', '');

        if (empty(trim($query))) {
            return response()->json([
                'success' => false,
                'message' => 'Le terme de recherche est requis',
            ], 400);
        }

        $prospects = $this->getProspectCollection()->searchByQuery($userId, $query);

        return response()->json([
            'success' => true,
            'data' => [
                'prospects' => array_map([$this, 'formatProspect'], $prospects),
                'total' => count($prospects),
                'query' => $query,
            ],
        ]);
    }

    private function getFiltersFromRequest(Request $request): array
    {
        $filters = [];

        if ($request->has('status')) {
            $filters['status'] = $request->get('status');
        }

        if ($request->has('sector')) {
            $filters['sector'] = $request->get('sector');
        }

        if ($request->has('city')) {
            $filters['city'] = $request->get('city');
        }

        if ($request->has('min_score')) {
            $filters['min_score'] = (int) $request->get('min_score');
        }

        if ($request->has('search')) {
            $filters['search'] = $request->get('search');
        }

        if ($request->has('order_by')) {
            $filters['order_by'] = $request->get('order_by');
            $filters['order_direction'] = $request->get('order_direction', 'desc');
        }

        if ($request->has('category_id')) {
            $filters['category_id'] = (int) $request->get('category_id');
        }

        if ($request->has('without_category')) {
            $filters['without_category'] = (bool) $request->get('without_category');
        }

        return $filters;
    }

    private function getBulkSaveMessage(array $results): string
    {
        $messages = [];
        
        if ($results['saved'] > 0) {
            $messages[] = "{$results['saved']} prospect(s) sauvegardé(s)";
        }
        
        if ($results['exists'] > 0) {
            $messages[] = "{$results['exists']} prospect(s) existaient déjà";
        }
        
        if ($results['errors'] > 0) {
            $messages[] = "{$results['errors']} erreur(s)";
        }
        
        return implode(', ', $messages);
    }

    /**
     * Enrichit les contacts web d'un prospect
     */
    public function enrichContacts(Request $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé'
            ], 404);
        }

        $validated = $request->validate([
            'force' => 'sometimes|boolean',
            'max_contacts' => 'sometimes|integer|min:1|max:50',
            'custom_urls' => 'sometimes|array',
            'custom_urls.*' => 'url'
        ]);

        try {
            $enrichmentService = app(ProspectEnrichmentService::class);
            
            $result = $enrichmentService->enrichProspectWebContacts($prospect, [
                'force' => $validated['force'] ?? false,
                'max_contacts' => $validated['max_contacts'] ?? 10,
                'urls_to_scrape' => $validated['custom_urls'] ?? null,
                'user_id' => $userId,
                'triggered_by' => 'user'
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Enrichissement terminé avec succès',
                    'data' => [
                        'contacts' => $result['contacts'],
                        'metadata' => $result['metadata'] ?? []
                    ]
                ]);
            } else {
                $statusCode = $result['reason'] === 'not_eligible' ? 422 : 400;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Enrichissement échoué',
                    'reason' => $result['reason'] ?? 'unknown',
                    'data' => $result['eligibility'] ?? null
                ], $statusCode);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enrichissement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifie l'éligibilité d'enrichissement d'un prospect
     */
    public function getEnrichmentEligibility(Request $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé'
            ], 404);
        }

        try {
            $enrichmentService = app(ProspectEnrichmentService::class);
            $eligibility = $enrichmentService->getProspectEnrichmentEligibility($prospect);

            return response()->json([
                'success' => true,
                'data' => $eligibility
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification d\'éligibilité',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtient l'historique d'enrichissement d'un prospect
     */
    public function getEnrichmentHistory(Request $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé'
            ], 404);
        }

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        try {
            $enrichmentService = app(ProspectEnrichmentService::class);
            $history = $enrichmentService->getProspectEnrichmentHistory(
                $id, 
                $validated['limit'] ?? 10
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'history' => $history,
                    'total' => count($history)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Blacklist un prospect pour l'enrichissement automatique
     */
    public function blacklistEnrichment(Request $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé'
            ], 404);
        }

        $validated = $request->validate([
            'reason' => 'sometimes|string|max:255'
        ]);

        try {
            $enrichmentService = app(ProspectEnrichmentService::class);
            $success = $enrichmentService->blacklistProspectEnrichment(
                $id, 
                $validated['reason'] ?? null
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Prospect blacklisté pour l\'enrichissement automatique'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du blacklistage'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du blacklistage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Active/désactive l'enrichissement automatique pour un prospect
     */
    public function toggleAutoEnrichment(Request $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $prospect = $this->getProspectCollection()->findById($id);
        
        if (!$prospect || $prospect->userId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé'
            ], 404);
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean'
        ]);

        try {
            $enrichmentService = app(ProspectEnrichmentService::class);
            $success = $enrichmentService->toggleAutoEnrichment(
                $id, 
                $validated['enabled']
            );

            if ($success) {
                $message = $validated['enabled'] 
                    ? 'Enrichissement automatique activé' 
                    : 'Enrichissement automatique désactivé';
                    
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la modification'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enrichissement par lot
     */
    public function bulkEnrichContacts(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'prospect_ids' => 'required|array|min:1|max:50',
            'prospect_ids.*' => 'integer|exists:prospects,id',
            'force' => 'sometimes|boolean',
            'max_processing' => 'sometimes|integer|min:1|max:20'
        ]);

        // Vérifier que tous les prospects appartiennent à l'utilisateur
        $userProspectIds = DB::table('prospects')
            ->where('user_id', $userId)
            ->whereIn('id', $validated['prospect_ids'])
            ->pluck('id')
            ->toArray();

        if (count($userProspectIds) !== count($validated['prospect_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Certains prospects ne vous appartiennent pas'
            ], 403);
        }

        try {
            $enrichmentService = app(ProspectEnrichmentService::class);
            
            $result = $enrichmentService->bulkEnrichProspectWebContacts($validated['prospect_ids'], [
                'force' => $validated['force'] ?? false,
                'max_processing' => $validated['max_processing'] ?? 10,
                'user_id' => $userId,
                'triggered_by' => 'bulk'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Enrichissement par lot terminé',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enrichissement par lot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtient les statistiques d'enrichissement de l'utilisateur
     */
    public function getEnrichmentStats(Request $request): JsonResponse
    {
        $userId = Auth::id();

        try {
            $enrichmentService = app(ProspectEnrichmentService::class);
            $stats = $enrichmentService->getEnrichmentEligibilityStats($userId);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function formatProspect(\App\__Domain__\Data\Prospect\Model $prospect): array
    {
        // Récupérer les informations de notes
        $notesInfo = DB::table('prospect_notes')
            ->where('prospect_id', $prospect->id)
            ->selectRaw('COUNT(*) as notes_count, MAX(created_at) as last_note_date')
            ->first();

        $lastNote = null;
        if ($notesInfo && $notesInfo->notes_count > 0) {
            $lastNote = DB::table('prospect_notes')
                ->where('prospect_id', $prospect->id)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // Récupérer les catégories du prospect
        $categories = DB::table('prospect_category_prospect')
            ->join('prospect_categories', 'prospect_categories.id', '=', 'prospect_category_prospect.prospect_category_id')
            ->where('prospect_category_prospect.prospect_id', $prospect->id)
            ->select('prospect_categories.*')
            ->orderBy('prospect_categories.position')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'position' => $category->position
                ];
            })
            ->toArray();

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
            'notes_count' => (int) ($notesInfo->notes_count ?? 0),
            'last_note' => $lastNote ? [
                'content' => $lastNote->content,
                'created_at' => $lastNote->created_at,
                'type' => $lastNote->type
            ] : null,
            'categories' => $categories,
        ];
    }
}