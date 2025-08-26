<?php

namespace App\__Infrastructure__\Services\User;

use App\__Infrastructure__\Eloquent\UserEloquent as User;
use App\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de gestion des abonnements utilisateur
 */
class UserSubscriptionService
{
    /**
     * Créer un nouvel abonnement premium
     */
    public function createPremiumSubscription(
        User $user,
        string $planType = 'premium_monthly',
        ?Carbon $startsAt = null,
        ?Carbon $expiresAt = null,
        ?float $amount = null,
        ?string $paymentMethod = null,
        ?string $externalSubscriptionId = null
    ): UserSubscription {
        $startsAt = $startsAt ?? now();
        
        if (!$expiresAt) {
            $expiresAt = $planType === 'premium_yearly' 
                ? $startsAt->copy()->addYear()
                : $startsAt->copy()->addMonth();
        }

        // Handle transaction safely - if we're already in a transaction (like during tests), don't start a new one
        $callback = function () use ($user, $planType, $startsAt, $expiresAt, $amount, $paymentMethod, $externalSubscriptionId) {
            // Désactiver les anciens abonnements
            $this->deactivateUserSubscriptions($user);

            // Créer le nouvel abonnement
            $subscription = UserSubscription::create([
                'user_id' => $user->id,
                'plan_type' => $planType,
                'status' => 'active',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'external_subscription_id' => $externalSubscriptionId,
            ]);

            // Mettre à jour le statut utilisateur
            $user->upgradeToPremium($expiresAt);

            // Réinitialiser le compteur de recherches
            $user->update([
                'daily_searches_count' => 0,
                'daily_searches_reset_at' => now(),
            ]);

            Log::info('Abonnement premium créé', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_type' => $planType,
                'expires_at' => $expiresAt->toDateTimeString()
            ]);

            return $subscription;
        };

        // If we're already in a transaction (e.g., during tests), just execute the callback
        if (DB::transactionLevel() > 0) {
            return $callback();
        }
        
        // Otherwise, wrap in a transaction
        return DB::transaction($callback);
    }

    /**
     * Annuler l'abonnement d'un utilisateur
     */
    public function cancelSubscription(User $user, ?string $reason = null): bool
    {
        $callback = function () use ($user, $reason) {
            // Désactiver tous les abonnements actifs
            $cancelled = $this->deactivateUserSubscriptions($user, 'cancelled');

            // Downgrader l'utilisateur vers gratuit
            $user->downgradeToFree();

            Log::info('Abonnement annulé', [
                'user_id' => $user->id,
                'reason' => $reason,
                'cancelled_subscriptions' => $cancelled
            ]);

            return true;
        };

        // If we're already in a transaction (e.g., during tests), just execute the callback
        if (DB::transactionLevel() > 0) {
            return $callback();
        }
        
        // Otherwise, wrap in a transaction
        return DB::transaction($callback);
    }

    /**
     * Renouveler un abonnement
     */
    public function renewSubscription(
        User $user, 
        ?Carbon $newExpiresAt = null,
        ?float $amount = null
    ): ?UserSubscription {
        $currentSubscription = $user->currentSubscription()->first();
        
        if (!$currentSubscription) {
            return null;
        }

        $newExpiresAt = $newExpiresAt ?? $currentSubscription->expires_at->copy()->addMonth();

        try {
            // Prolonger l'abonnement existant
            $currentSubscription->update(['expires_at' => $newExpiresAt]);

            // Mettre à jour l'utilisateur
            $user->update(['subscription_expires_at' => $newExpiresAt]);

            // Créer un nouvel enregistrement d'abonnement pour l'historique
            $newSubscription = UserSubscription::create([
                'user_id' => $user->id,
                'plan_type' => $currentSubscription->plan_type,
                'status' => 'active',
                'starts_at' => $currentSubscription->expires_at,
                'expires_at' => $newExpiresAt,
                'amount' => $amount ?? $currentSubscription->amount,
                'payment_method' => $currentSubscription->payment_method,
                'external_subscription_id' => $currentSubscription->external_subscription_id,
            ]);

            Log::info('Abonnement renouvelé', [
                'user_id' => $user->id,
                'old_subscription_id' => $currentSubscription->id,
                'new_subscription_id' => $newSubscription->id,
                'new_expires_at' => $newExpiresAt->toDateTimeString()
            ]);

            return $newSubscription;

        } catch (\Exception $e) {
            Log::error('Erreur lors du renouvellement d\'abonnement', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Vérifier et traiter les abonnements expirés
     */
    public function processExpiredSubscriptions(): int
    {
        $expiredSubscriptions = UserSubscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('user')
            ->get();

        $processedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            try {
                // Marquer l'abonnement comme expiré
                $subscription->markAsExpired();

                // Downgrader l'utilisateur vers gratuit
                $subscription->user->downgradeToFree();

                $processedCount++;

                Log::info('Abonnement expiré traité', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'expired_at' => $subscription->expires_at->toDateTimeString()
                ]);

            } catch (\Exception $e) {
                Log::error('Erreur lors du traitement d\'abonnement expiré', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processedCount;
    }

    /**
     * Obtenir les statistiques des abonnements
     */
    public function getSubscriptionStats(): array
    {
        return [
            'total_users' => User::count(),
            'premium_users' => User::premium()->count(),
            'free_users' => User::free()->count(),
            'active_subscriptions' => UserSubscription::active()->count(),
            'expired_subscriptions' => UserSubscription::expired()->count(),
            'monthly_revenue' => UserSubscription::active()
                ->whereIn('plan_type', ['premium_monthly'])
                ->sum('amount'),
            'yearly_revenue' => UserSubscription::active()
                ->whereIn('plan_type', ['premium_yearly'])
                ->sum('amount'),
        ];
    }

    /**
     * Désactiver tous les abonnements d'un utilisateur
     */
    private function deactivateUserSubscriptions(User $user, string $status = 'expired'): int
    {
        return UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => $status]);
    }
}