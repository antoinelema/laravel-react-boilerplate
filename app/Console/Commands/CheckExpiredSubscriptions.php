<?php

namespace App\Console\Commands;

use App\__Infrastructure__\Services\User\UserSubscriptionService;
use Illuminate\Console\Command;

class CheckExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifie et traite les abonnements expirés';

    /**
     * Execute the console command.
     */
    public function handle(UserSubscriptionService $subscriptionService)
    {
        $this->info('Vérification des abonnements expirés...');

        try {
            $processedCount = $subscriptionService->processExpiredSubscriptions();

            $this->info("✅ {$processedCount} abonnements expirés traités avec succès.");
            
            // Afficher des statistiques
            $stats = $subscriptionService->getSubscriptionStats();
            $this->table(
                ['Métrique', 'Valeur'],
                [
                    ['Total utilisateurs', $stats['total_users']],
                    ['Utilisateurs premium', $stats['premium_users']],
                    ['Utilisateurs gratuits', $stats['free_users']],
                    ['Abonnements actifs', $stats['active_subscriptions']],
                    ['Abonnements expirés', $stats['expired_subscriptions']],
                    ['Revenus mensuels', number_format($stats['monthly_revenue'], 2) . ' €'],
                    ['Revenus annuels', number_format($stats['yearly_revenue'], 2) . ' €'],
                    ['Abonnements expirés traités', $processedCount],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la vérification des abonnements: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
