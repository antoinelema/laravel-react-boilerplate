<?php

namespace App\Console\Commands;

use App\__Infrastructure__\Persistence\Eloquent\User;
use App\__Infrastructure__\Services\User\UserSubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpgradeUserToPremium extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:upgrade-premium 
                            {email : Email de l\'utilisateur à upgrader}
                            {--plan=premium_monthly : Type d\'abonnement (premium_monthly|premium_yearly)}
                            {--duration=30 : Durée en jours}
                            {--amount= : Montant payé (optionnel)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade un utilisateur vers Premium manuellement';

    /**
     * Execute the console command.
     */
    public function handle(UserSubscriptionService $subscriptionService)
    {
        $email = $this->argument('email');
        $planType = $this->option('plan');
        $duration = (int) $this->option('duration');
        $amount = $this->option('amount') ? (float) $this->option('amount') : null;

        // Validation du plan
        if (!in_array($planType, ['premium_monthly', 'premium_yearly'])) {
            $this->error('❌ Plan invalide. Utilisez premium_monthly ou premium_yearly.');
            return self::FAILURE;
        }

        try {
            // Trouver l'utilisateur
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $this->error("❌ Utilisateur avec l'email {$email} non trouvé.");
                return self::FAILURE;
            }

            $this->info("👤 Utilisateur trouvé: {$user->name} ({$user->email})");
            $this->info("📊 Statut actuel: " . ($user->isPremium() ? 'Premium' : 'Gratuit'));

            if ($user->isPremium()) {
                $currentExpiry = $user->subscription_expires_at?->format('Y-m-d H:i:s');
                $this->warn("⚠️  L'utilisateur est déjà Premium (expire le {$currentExpiry})");
                
                if (!$this->confirm('Voulez-vous prolonger/renouveler l\'abonnement ?')) {
                    return self::SUCCESS;
                }
            }

            // Calculer les dates
            $startsAt = now();
            $expiresAt = $startsAt->copy()->addDays($duration);

            $this->info("📅 Période: du {$startsAt->format('Y-m-d')} au {$expiresAt->format('Y-m-d')} ({$duration} jours)");

            if ($amount) {
                $this->info("💰 Montant: {$amount} €");
            }

            if (!$this->confirm('Confirmer l\'upgrade ?')) {
                $this->info('❌ Upgrade annulé.');
                return self::SUCCESS;
            }

            // Créer l'abonnement premium
            $subscription = $subscriptionService->createPremiumSubscription(
                $user,
                $planType,
                $startsAt,
                $expiresAt,
                $amount,
                'manual_admin',
                null
            );

            $this->info('✅ Utilisateur upgradé vers Premium avec succès!');
            
            $this->table(
                ['Détail', 'Valeur'],
                [
                    ['ID Abonnement', $subscription->id],
                    ['Utilisateur', $user->name . ' (' . $user->email . ')'],
                    ['Plan', $planType],
                    ['Débute le', $startsAt->format('Y-m-d H:i:s')],
                    ['Expire le', $expiresAt->format('Y-m-d H:i:s')],
                    ['Montant', $amount ? $amount . ' €' : 'Non spécifié'],
                    ['Statut', $subscription->status],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de l\'upgrade: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
