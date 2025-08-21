<?php

namespace App\__Domain__\UseCase\Prospect\Save;

use App\__Domain__\Data\Prospect\Collection as ProspectCollection;
use App\__Domain__\Data\Prospect\Factory as ProspectFactory;
use App\__Domain__\Data\ProspectSearch\Collection as ProspectSearchCollection;
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;
use Illuminate\Support\Facades\DB;

/**
 * Handler pour la sauvegarde de prospects
 */
class Handler
{
    private ProspectCollection $prospectCollection;
    private ProspectSearchCollection $searchCollection;
    private ProspectEnrichmentService $enrichmentService;

    public function __construct(
        ProspectCollection $prospectCollection,
        ProspectSearchCollection $searchCollection,
        ProspectEnrichmentService $enrichmentService
    ) {
        $this->prospectCollection = $prospectCollection;
        $this->searchCollection = $searchCollection;
        $this->enrichmentService = $enrichmentService;
    }

    public function handle(Input $input): Output
    {
        try {
            // Validation des données d'entrée
            if (!$input->prospect && !$input->prospectData) {
                return Output::failure('Aucune donnée de prospect fournie');
            }

            // Création du prospect si nécessaire
            $prospect = $input->prospect;
            if (!$prospect) {
                $prospect = $this->createProspectFromData($input->userId, $input->prospectData);
            }

            // Vérification si le prospect existe déjà (par external_id et source)
            $existingProspect = $this->findExistingProspect($prospect);
            if ($existingProspect) {
                $this->updateSearchStats($input->searchId);
                return Output::alreadyExists($existingProspect);
            }

            // Enrichissement du prospect avec des données complémentaires
            $enrichedProspect = $this->enrichmentService->enrichProspect($prospect);

            // Sauvegarde du prospect
            $savedProspect = $this->prospectCollection->save($enrichedProspect);

            // Création d'une note si fournie
            if ($input->note && !empty(trim($input->note))) {
                $this->createNote($savedProspect->id, $input->userId, $input->note);
            }

            // Mise à jour des statistiques de recherche
            $this->updateSearchStats($input->searchId);

            return Output::success($savedProspect);

        } catch (\Exception $e) {
            return Output::failure('Erreur lors de la sauvegarde: ' . $e->getMessage());
        }
    }

    private function createProspectFromData(int $userId, array $data): \App\__Domain__\Data\Prospect\Model
    {
        $data['user_id'] = $userId;
        return ProspectFactory::create($data);
    }

    private function findExistingProspect(\App\__Domain__\Data\Prospect\Model $prospect): ?\App\__Domain__\Data\Prospect\Model
    {
        if ($prospect->externalId && $prospect->source) {
            return $this->prospectCollection->findByExternalId($prospect->externalId, $prospect->source);
        }

        // Recherche par nom et ville pour éviter les doublons manuels
        $existingProspects = $this->prospectCollection->findByUserIdWithFilters($prospect->userId, [
            'search' => $prospect->name,
            'city' => $prospect->city
        ]);

        // Vérifier s'il y a une correspondance exacte
        foreach ($existingProspects as $existing) {
            if ($this->areProspectsSimilar($prospect, $existing)) {
                return $existing;
            }
        }

        return null;
    }

    private function areProspectsSimilar(\App\__Domain__\Data\Prospect\Model $prospect1, \App\__Domain__\Data\Prospect\Model $prospect2): bool
    {
        // Comparaison basée sur le nom et la localisation
        $name1 = strtolower(trim($prospect1->name));
        $name2 = strtolower(trim($prospect2->name));
        
        $city1 = strtolower(trim($prospect1->city ?? ''));
        $city2 = strtolower(trim($prospect2->city ?? ''));

        // Seuil de similarité pour les noms (permettre quelques différences)
        $namesSimilar = (similar_text($name1, $name2) / max(strlen($name1), strlen($name2))) > 0.8;
        $citiesSimilar = empty($city1) || empty($city2) || $city1 === $city2;

        return $namesSimilar && $citiesSimilar;
    }

    private function updateSearchStats(?int $searchId): void
    {
        if (!$searchId) {
            return;
        }

        try {
            $search = $this->searchCollection->findById($searchId);
            if ($search) {
                $search->incrementSavedCount();
                $this->searchCollection->save($search);
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas échouer la sauvegarde
            \Illuminate\Support\Facades\Log::warning('Failed to update search stats', [
                'search_id' => $searchId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function createNote(int $prospectId, int $userId, string $noteContent): void
    {
        try {
            DB::table('prospect_notes')->insert([
                'prospect_id' => $prospectId,
                'user_id' => $userId,
                'content' => $noteContent,
                'type' => 'note',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas échouer la sauvegarde
            \Illuminate\Support\Facades\Log::warning('Failed to create note for prospect', [
                'prospect_id' => $prospectId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}