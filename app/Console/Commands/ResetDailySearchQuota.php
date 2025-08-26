<?php

namespace App\Console\Commands;

use App\__Infrastructure__\Services\User\SearchQuotaService;
use Illuminate\Console\Command;

class ResetDailySearchQuota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quota:reset-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Réinitialise les quotas de recherche quotidiens pour tous les utilisateurs gratuits';

    /**
     * Execute the console command.
     */
    public function handle(SearchQuotaService $searchQuotaService)
    {
        $this->info('Démarrage de la réinitialisation des quotas quotidiens...');

        try {
            $resetCount = $searchQuotaService->resetAllDailyQuotas();

            $this->info("✅ Quotas réinitialisés avec succès pour {$resetCount} utilisateurs.");
            
            // Afficher des statistiques
            $stats = $searchQuotaService->getSearchUsageStats();
            $this->table(
                ['Métrique', 'Valeur'],
                [
                    ['Total utilisateurs', $stats['total_users']],
                    ['Utilisateurs premium', $stats['premium_users']],
                    ['Utilisateurs gratuits', $stats['free_users']],
                    ['Utilisateurs réinitialisés', $resetCount],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la réinitialisation des quotas: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
