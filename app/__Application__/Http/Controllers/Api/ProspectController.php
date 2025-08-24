<?php

namespace App\__Application__\Http\Controllers\Api;

use App\__Application__\Http\Requests\SaveProspectRequest;
use App\__Domain__\Data\Prospect\Collection as ProspectCollection;
use App\__Domain__\UseCase\Prospect\Save\Handler as SaveHandler;
use App\__Domain__\UseCase\Prospect\Save\Input as SaveInput;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        ];
    }
}