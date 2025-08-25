<?php

namespace App\Console\Commands;

use App\__Infrastructure__\Services\Enrichment\EnrichmentEligibilityService;
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoEnrichProspects extends Command
{
    protected $signature = 'prospects:auto-enrich 
                          {--limit=10 : Nombre maximum de prospects à enrichir}
                          {--force-refresh-days=90 : Forcer le refresh après X jours}
                          {--user-id= : Enrichir uniquement pour un utilisateur spécifique}
                          {--dry-run : Simulation sans exécution réelle}
                          {--max-attempts=3 : Nombre maximum de tentatives par prospect}
                          {--delay=2 : Délai en secondes entre chaque enrichissement}';

    protected $description = 'Enrichit automatiquement les prospects éligibles avec leurs contacts web';

    private EnrichmentEligibilityService $eligibilityService;
    private ProspectEnrichmentService $enrichmentService;

    public function __construct(
        EnrichmentEligibilityService $eligibilityService,
        ProspectEnrichmentService $enrichmentService
    ) {
        parent::__construct();
        $this->eligibilityService = $eligibilityService;
        $this->enrichmentService = $enrichmentService;
    }

    public function handle(): int
    {
        $this->info('🚀 Démarrage de l\'enrichissement automatique des prospects');
        
        $limit = (int) $this->option('limit');
        $forceRefreshDays = (int) $this->option('force-refresh-days');
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;
        $dryRun = $this->option('dry-run');
        $maxAttempts = (int) $this->option('max-attempts');
        $delay = (int) $this->option('delay');
        
        $this->info("Configuration :");
        $this->line("  • Limite : {$limit} prospects");
        $this->line("  • Refresh forcé après : {$forceRefreshDays} jours");
        $this->line("  • Utilisateur : " . ($userId ? $userId : 'Tous'));
        $this->line("  • Mode simulation : " . ($dryRun ? 'Oui' : 'Non'));
        $this->line("  • Max tentatives : {$maxAttempts}");
        $this->line("  • Délai entre enrichissements : {$delay}s");
        $this->newLine();

        try {
            // Obtenir les prospects éligibles
            $eligibilityOptions = [
                'refresh_after_days' => $forceRefreshDays,
                'max_attempts' => $maxAttempts,
                'force_mode' => false
            ];
            
            $prospectIds = $userId ? $this->getProspectIdsForUser($userId) : null;
            $eligibleProspects = $this->eligibilityService->getEligibleProspects($prospectIds, $eligibilityOptions)
                                                          ->take($limit);

            $this->info("📊 Analyse d'éligibilité :");
            $this->line("  • Prospects éligibles trouvés : {$eligibleProspects->count()}");
            
            if ($eligibleProspects->isEmpty()) {
                $this->warn('Aucun prospect éligible trouvé.');
                return self::SUCCESS;
            }

            // Mode simulation
            if ($dryRun) {
                $this->info("\n🔍 Mode simulation - Prospects qui seraient enrichis :");
                $this->displayEligibleProspects($eligibleProspects);
                return self::SUCCESS;
            }

            // Traitement réel
            $results = $this->processEnrichments($eligibleProspects, $delay);
            
            // Afficher les résultats
            $this->displayResults($results);
            
            // Log final
            Log::info('Auto-enrichment completed', [
                'processed' => $results['processed'],
                'failed' => $results['failed'],
                'total_eligible' => $eligibleProspects->count(),
                'execution_time' => $results['execution_time']
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de l'enrichissement automatique : " . $e->getMessage());
            Log::error('Auto-enrichment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    private function getProspectIdsForUser(int $userId): array
    {
        return \DB::table('prospects')
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();
    }

    private function displayEligibleProspects($prospects): void
    {
        $headers = ['ID', 'Nom', 'Entreprise', 'Dernier enrichissement', 'Score complétude', 'Priorité'];
        $rows = [];

        foreach ($prospects as $prospectEloquent) {
            $prospect = $prospectEloquent->toDomainModel();
            $eligibility = $this->eligibilityService->isEligibleForEnrichment($prospect);
            
            $rows[] = [
                $prospect->id,
                $prospect->name ?: 'N/A',
                $prospect->company ?: 'N/A',
                $prospectEloquent->last_enrichment_at ? 
                    $prospectEloquent->last_enrichment_at->diffForHumans() : 'Jamais',
                $prospectEloquent->data_completeness_score . '%',
                $eligibility['priority'] ?? 'unknown'
            ];
        }

        $this->table($headers, $rows);
    }

    private function processEnrichments($prospects, int $delay): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'execution_time' => 0
        ];

        $startTime = microtime(true);
        $bar = $this->output->createProgressBar($prospects->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        
        $this->info("🔄 Traitement des prospects...\n");
        $bar->start();

        foreach ($prospects as $prospectEloquent) {
            try {
                $prospect = $prospectEloquent->toDomainModel();
                $bar->setMessage("Enrichissement: {$prospect->name} ({$prospect->company})");

                $enrichmentResult = $this->enrichmentService->enrichProspectWebContacts($prospect, [
                    'triggered_by' => 'auto',
                    'force' => false
                ]);

                if ($enrichmentResult['success']) {
                    $results['processed']++;
                    $contactsCount = count($enrichmentResult['contacts'] ?? []);
                    $bar->setMessage("✅ {$prospect->name}: {$contactsCount} contacts trouvés");
                } elseif ($enrichmentResult['reason'] === 'not_eligible') {
                    $results['skipped']++;
                    $bar->setMessage("⏭️  {$prospect->name}: Non éligible");
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'prospect_id' => $prospect->id,
                        'name' => $prospect->name,
                        'reason' => $enrichmentResult['reason'] ?? 'unknown'
                    ];
                    $bar->setMessage("❌ {$prospect->name}: Échec");
                }

                $bar->advance();
                
                // Délai respectueux entre les traitements
                if ($delay > 0) {
                    sleep($delay);
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'prospect_id' => $prospect->id ?? null,
                    'name' => $prospect->name ?? 'Inconnu',
                    'error' => $e->getMessage()
                ];
                
                $bar->setMessage("❌ Erreur: " . $e->getMessage());
                $bar->advance();
            }
        }

        $bar->finish();
        $results['execution_time'] = round(microtime(true) - $startTime, 2);
        
        return $results;
    }

    private function displayResults(array $results): void
    {
        $this->newLine(2);
        $this->info("📈 Résultats de l'enrichissement automatique :");
        $this->line("  • ✅ Prospects enrichis avec succès : {$results['processed']}");
        $this->line("  • ❌ Échecs : {$results['failed']}");
        $this->line("  • ⏭️  Ignorés (non éligibles) : {$results['skipped']}");
        $this->line("  • ⏱️  Temps d'exécution : {$results['execution_time']}s");

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->warn("⚠️  Détails des erreurs :");
            foreach ($results['errors'] as $error) {
                $this->line("  • #{$error['prospect_id']} {$error['name']}: " . 
                          ($error['error'] ?? $error['reason'] ?? 'Erreur inconnue'));
            }
        }

        // Recommandations
        $this->newLine();
        if ($results['processed'] > 0) {
            $this->info("💡 Recommandations :");
            $this->line("  • Vérifiez les nouveaux contacts dans l'interface web");
            $this->line("  • Configurez des alertes pour les prospects enrichis");
        }

        if ($results['failed'] > 0) {
            $this->warn("  • Consultez les logs pour plus de détails sur les échecs");
            $this->line("  • Considérez ajuster les paramètres d'éligibilité");
        }
    }

    /**
     * Obtient les statistiques avant traitement
     */
    public function getPreProcessingStats(): array
    {
        return $this->eligibilityService->getEligibilityStats();
    }

    /**
     * Affiche l'aide étendue
     */
    public function displayHelp(): void
    {
        $this->info("📚 Aide pour la commande d'enrichissement automatique");
        $this->newLine();
        
        $this->line("Exemples d'utilisation :");
        $this->line("  • Simulation : <comment>sail artisan prospects:auto-enrich --dry-run</comment>");
        $this->line("  • 5 prospects max : <comment>sail artisan prospects:auto-enrich --limit=5</comment>");
        $this->line("  • Utilisateur spécifique : <comment>sail artisan prospects:auto-enrich --user-id=123</comment>");
        $this->line("  • Mode agressif : <comment>sail artisan prospects:auto-enrich --force-refresh-days=7</comment>");
        $this->newLine();
        
        $this->line("Programmation cron suggérée :");
        $this->line("  <comment># Tous les jours à 2h du matin, max 20 prospects</comment>");
        $this->line("  <comment>0 2 * * * php artisan prospects:auto-enrich --limit=20</comment>");
        $this->newLine();
        
        $this->line("  <comment># Toutes les heures en journée (mode conservateur)</comment>");
        $this->line("  <comment>0 9-18 * * * php artisan prospects:auto-enrich --limit=5 --delay=3</comment>");
    }
}
