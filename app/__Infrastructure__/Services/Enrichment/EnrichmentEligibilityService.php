<?php

namespace App\__Infrastructure__\Services\Enrichment;

use App\__Domain__\Data\Prospect\Model as ProspectModel;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EnrichmentEligibilityService
{
    private array $defaultOptions;

    public function __construct()
    {
        $this->defaultOptions = [
            'refresh_after_days' => config('services.web_enrichment.refresh_after_days', 30),
            'min_completeness_score' => config('services.web_enrichment.min_completeness_score', 80),
            'max_attempts' => config('services.web_enrichment.max_attempts', 3),
            'force_mode' => false
        ];
    }

    /**
     * Obtient tous les prospects éligibles à l'enrichissement
     */
    public function getEligibleProspects(array $prospectIds = null, array $options = []): Collection
    {
        $options = array_merge($this->defaultOptions, $options);
        $query = ProspectEloquent::query();
        
        if ($prospectIds) {
            $query->whereIn('id', $prospectIds);
        }
        
        if (!$options['force_mode']) {
            $query = $this->applyEligibilityFilters($query, $options);
        }
        
        return $query
            ->orderByRaw($this->getEligibilityOrderBy())
            ->get();
    }

    /**
     * Vérifie si un prospect est éligible à l'enrichissement
     */
    public function isEligibleForEnrichment(ProspectModel $prospect, array $options = [], ?ProspectEloquent $prospectEloquent = null): array
    {
        $options = array_merge($this->defaultOptions, $options);
        $reasons = [];
        
        // Mode force : toujours éligible
        if ($options['force_mode']) {
            return [
                'is_eligible' => true,
                'reason' => 'forced',
                'next_eligible_at' => null,
                'completeness_score' => $this->calculateCompletenessScore($prospect),
                'details' => ['Force mode activated']
            ];
        }

        // Si pas de modèle Eloquent fourni, récupérer les données d'enrichissement depuis la DB
        if (!$prospectEloquent && $prospect->id) {
            $prospectEloquent = ProspectEloquent::find($prospect->id);
        }

        // Vérifications de base
        if ($prospectEloquent && $prospectEloquent->auto_enrich_enabled === false) {
            $reasons[] = 'Auto-enrichment disabled';
            return $this->ineligibleResponse('disabled', $reasons, $prospect, null, null, $prospectEloquent);
        }

        if ($prospectEloquent && $prospectEloquent->enrichment_blacklisted_at) {
            $reasons[] = 'Prospect blacklisted on ' . $prospectEloquent->enrichment_blacklisted_at->format('Y-m-d');
            return $this->ineligibleResponse('blacklisted', $reasons, $prospect, null, null, $prospectEloquent);
        }

        if ($prospectEloquent && $prospectEloquent->enrichment_status === 'pending') {
            $reasons[] = 'Enrichment currently in progress';
            return $this->ineligibleResponse('in_progress', $reasons, $prospect, null, null, $prospectEloquent);
        }

        // Vérification du score de complétude
        $completenessScore = $this->calculateCompletenessScore($prospect);
        if ($completenessScore >= $options['min_completeness_score']) {
            $reasons[] = "Data already complete (score: {$completenessScore}%)";
            return $this->ineligibleResponse('complete_data', $reasons, $prospect, $completenessScore, null, $prospectEloquent);
        }

        // Vérification de la fraîcheur de l'enrichissement
        if ($prospectEloquent && $prospectEloquent->last_enrichment_at) {
            $daysSinceLastEnrichment = $prospectEloquent->last_enrichment_at->diffInDays(now());
            if ($daysSinceLastEnrichment < $options['refresh_after_days']) {
                $nextEligible = $prospectEloquent->last_enrichment_at->addDays($options['refresh_after_days']);
                $reasons[] = "Recently enriched {$daysSinceLastEnrichment} days ago";
                return $this->ineligibleResponse('recently_enriched', $reasons, $prospect, $completenessScore, $nextEligible, $prospectEloquent);
            }
        }

        // Vérification du nombre max de tentatives
        if ($prospectEloquent && $prospectEloquent->enrichment_attempts >= $options['max_attempts'] && 
            $prospectEloquent->enrichment_status === 'failed') {
            $attempts = $prospectEloquent->enrichment_attempts;
            $reasons[] = "Maximum attempts reached ({$attempts}/{$options['max_attempts']})";
            return $this->ineligibleResponse('max_attempts_reached', $reasons, $prospect, $completenessScore, null, $prospectEloquent);
        }

        // Éligible !
        return [
            'is_eligible' => true,
            'reason' => $this->getEligibilityReason($prospect, $prospectEloquent),
            'next_eligible_at' => null,
            'completeness_score' => $completenessScore,
            'priority' => $this->calculatePriority($prospect, $completenessScore, $prospectEloquent),
            'details' => $this->getEligibilityDetails($prospect, $completenessScore, $prospectEloquent)
        ];
    }

    /**
     * Calcule le score de complétude des données d'un prospect
     */
    public function calculateCompletenessScore(ProspectModel $prospect): float
    {
        $score = 0;
        $maxScore = 100;

        // Données de base (50 points)
        if (!empty($prospect->name)) $score += 15;
        if (!empty($prospect->company)) $score += 15;
        if (!empty($prospect->city)) $score += 10;
        if (!empty($prospect->address)) $score += 10;

        // Contacts principaux (40 points)
        $contactInfo = $prospect->contactInfo ?? [];
        if (!empty($contactInfo['email'])) $score += 20;
        if (!empty($contactInfo['phone'])) $score += 15;
        if (!empty($contactInfo['website'])) $score += 5;

        // Données enrichies (10 points)
        if (!empty($prospect->enrichment_data)) {
            $enrichmentData = is_string($prospect->enrichment_data) 
                ? json_decode($prospect->enrichment_data, true) 
                : $prospect->enrichment_data;
            
            if (is_array($enrichmentData) && count(array_filter($enrichmentData)) > 0) {
                $score += 10;
            }
        }

        return min($maxScore, $score);
    }

    /**
     * Met à jour le score de complétude d'un prospect
     */
    public function updateCompletenessScore(int $prospectId): float
    {
        $prospect = ProspectEloquent::find($prospectId);
        if (!$prospect) {
            throw new \Exception("Prospect {$prospectId} not found");
        }

        $score = $this->calculateCompletenessScore($prospect->toDomainModel());
        
        $prospect->update([
            'data_completeness_score' => $score
        ]);

        return $score;
    }

    /**
     * Met à jour les scores de complétude par lot
     */
    public function bulkUpdateCompletenessScores(array $prospectIds = null): array
    {
        $query = ProspectEloquent::query();
        
        if ($prospectIds) {
            $query->whereIn('id', $prospectIds);
        }
        
        $prospects = $query->get();
        $updated = [];
        
        foreach ($prospects as $prospect) {
            $score = $this->calculateCompletenessScore($prospect->toDomainModel());
            $prospect->update(['data_completeness_score' => $score]);
            $updated[$prospect->id] = $score;
        }

        Log::info('Bulk completeness scores updated', [
            'count' => count($updated),
            'prospect_ids' => array_keys($updated)
        ]);

        return $updated;
    }

    /**
     * Obtient des statistiques d'éligibilité
     */
    public function getEligibilityStats(int $userId = null): array
    {
        $query = ProspectEloquent::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = $query->count();
        
        // Répartition par statut d'enrichissement
        $byStatus = $query->groupBy('enrichment_status')
                         ->selectRaw('enrichment_status, COUNT(*) as count')
                         ->pluck('count', 'enrichment_status')
                         ->toArray();

        // Prospects éligibles actuellement
        $eligible = $this->getEligibleProspects(null, [])->count();
        
        // Prospects avec données complètes
        $complete = $query->where('data_completeness_score', '>=', $this->defaultOptions['min_completeness_score'])
                         ->count();

        // Prospects enrichis récemment
        $recentlyEnriched = $query->where('last_enrichment_at', '>=', 
                                        now()->subDays($this->defaultOptions['refresh_after_days']))
                                 ->count();

        return [
            'total_prospects' => $total,
            'eligible_for_enrichment' => $eligible,
            'complete_data' => $complete,
            'recently_enriched' => $recentlyEnriched,
            'never_enriched' => $byStatus['never'] ?? 0,
            'enrichment_pending' => $byStatus['pending'] ?? 0,
            'enrichment_failed' => $byStatus['failed'] ?? 0,
            'blacklisted' => ProspectEloquent::whereNotNull('enrichment_blacklisted_at')->count(),
            'completion_rate' => $total > 0 ? round(($complete / $total) * 100, 2) : 0,
            'enrichment_coverage' => $total > 0 ? round((($total - ($byStatus['never'] ?? 0)) / $total) * 100, 2) : 0
        ];
    }

    /**
     * Applique les filtres d'éligibilité à la requête
     */
    private function applyEligibilityFilters($query, array $options)
    {
        return $query
            ->where(function($q) use ($options) {
                // Jamais enrichi OU enrichi il y a longtemps OU échec précédent
                $q->whereNull('last_enrichment_at')
                  ->orWhere('last_enrichment_at', '<', now()->subDays($options['refresh_after_days']))
                  ->orWhere(function($subQ) use ($options) {
                      // Cas des échecs: permettre retry si pas encore max attempts
                      $subQ->where('enrichment_status', 'failed')
                           ->where('enrichment_attempts', '<', $options['max_attempts']);
                  });
            })
            // Pas en cours d'enrichissement
            ->where('enrichment_status', '!=', 'pending')
            // Pas blacklisté
            ->whereNull('enrichment_blacklisted_at')
            // Auto-enrichissement activé
            ->where('auto_enrich_enabled', true)
            // Données incomplètes
            ->where('data_completeness_score', '<', $options['min_completeness_score']);
    }

    /**
     * Génère l'ordre de priorité pour l'enrichissement
     */
    private function getEligibilityOrderBy(): string
    {
        return '
            CASE 
                WHEN last_enrichment_at IS NULL THEN 1
                WHEN enrichment_status = "failed" THEN 2
                ELSE 3
            END,
            data_completeness_score ASC,
            enrichment_attempts ASC,
            created_at DESC
        ';
    }

    /**
     * Calcule la priorité d'enrichissement
     */
    private function calculatePriority(ProspectModel $prospect, float $completenessScore, ?ProspectEloquent $prospectEloquent = null): string
    {
        $lastEnrichment = $prospectEloquent ? $prospectEloquent->last_enrichment_at : null;
        if (!$lastEnrichment) {
            return 'high'; // Jamais enrichi
        }
        
        $enrichmentStatus = $prospectEloquent ? $prospectEloquent->enrichment_status : null;
        $enrichmentAttempts = $prospectEloquent ? $prospectEloquent->enrichment_attempts : 0;
        
        if ($enrichmentStatus === 'failed' && $enrichmentAttempts < 2) {
            return 'high'; // Échec récent, retry nécessaire
        }
        
        if ($completenessScore < 30) {
            return 'high'; // Données très incomplètes
        }
        
        if ($completenessScore < 60) {
            return 'medium'; // Données moyennement incomplètes
        }
        
        return 'low'; // Données relativement complètes
    }

    /**
     * Obtient la raison d'éligibilité
     */
    private function getEligibilityReason(ProspectModel $prospect, ?ProspectEloquent $prospectEloquent = null): string
    {
        $lastEnrichment = $prospectEloquent ? $prospectEloquent->last_enrichment_at : null;
        $enrichmentStatus = $prospectEloquent ? $prospectEloquent->enrichment_status : null;
        
        if (!$lastEnrichment) {
            return 'never_enriched';
        }
        
        if ($enrichmentStatus === 'failed') {
            return 'previous_failure';
        }
        
        if ($lastEnrichment->diffInDays(now()) >= $this->defaultOptions['refresh_after_days']) {
            return 'outdated_enrichment';
        }
        
        return 'incomplete_data';
    }

    /**
     * Obtient les détails d'éligibilité
     */
    private function getEligibilityDetails(ProspectModel $prospect, float $completenessScore, ?ProspectEloquent $prospectEloquent = null): array
    {
        $details = [
            'completeness_score' => $completenessScore,
            'missing_data' => $this->getMissingDataTypes($prospect)
        ];
        
        $lastEnrichment = $prospectEloquent ? $prospectEloquent->last_enrichment_at : null;
        $enrichmentAttempts = $prospectEloquent ? $prospectEloquent->enrichment_attempts : 0;
        $enrichmentStatus = $prospectEloquent ? $prospectEloquent->enrichment_status : null;
        
        if ($lastEnrichment) {
            $details['days_since_last_enrichment'] = $lastEnrichment->diffInDays(now());
        }
        
        if ($enrichmentAttempts > 0) {
            $details['previous_attempts'] = $enrichmentAttempts;
            $details['last_status'] = $enrichmentStatus;
        }
        
        return $details;
    }

    /**
     * Identifie les types de données manquantes
     */
    private function getMissingDataTypes(ProspectModel $prospect): array
    {
        $missing = [];
        $contactInfo = $prospect->contactInfo ?? [];
        
        if (empty($contactInfo['email'])) $missing[] = 'email';
        if (empty($contactInfo['phone'])) $missing[] = 'phone';
        if (empty($contactInfo['website'])) $missing[] = 'website';
        if (empty($prospect->address)) $missing[] = 'address';
        if (empty($prospect->company)) $missing[] = 'company';
        
        return $missing;
    }

    /**
     * Génère une réponse d'inéligibilité standardisée
     */
    private function ineligibleResponse(
        string $reason, 
        array $reasonDetails, 
        ProspectModel $prospect, 
        float $completenessScore = null,
        Carbon $nextEligibleAt = null,
        ?ProspectEloquent $prospectEloquent = null
    ): array {
        $enrichmentStatus = $prospectEloquent ? $prospectEloquent->enrichment_status : null;
        $enrichmentAttempts = $prospectEloquent ? $prospectEloquent->enrichment_attempts : 0;
        $lastEnrichment = $prospectEloquent ? $prospectEloquent->last_enrichment_at : null;
        $blacklisted = $prospectEloquent ? $prospectEloquent->enrichment_blacklisted_at !== null : false;
        
        return [
            'is_eligible' => false,
            'reason' => $reason,
            'reason_details' => $reasonDetails,
            'next_eligible_at' => $nextEligibleAt,
            'completeness_score' => $completenessScore ?? $this->calculateCompletenessScore($prospect),
            'details' => [
                'enrichment_status' => $enrichmentStatus,
                'attempts' => $enrichmentAttempts,
                'last_enrichment' => $lastEnrichment?->toISOString(),
                'blacklisted' => $blacklisted
            ]
        ];
    }

    /**
     * Obtient les options par défaut
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    /**
     * Met à jour les options par défaut
     */
    public function setDefaultOptions(array $options): void
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);
    }
}