<?php

namespace App\__Infrastructure__\Services\User;

use App\__Infrastructure__\Persistence\Eloquent\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion des quotas de recherche
 */
class SearchQuotaService
{
    /**
     * Vérifier si l'utilisateur peut effectuer une recherche
     */
    public function canUserSearch(User $user): bool
    {
        // Les admins et les utilisateurs premium n'ont pas de limites
        if ($user->isAdmin() || $user->isPremium()) {
            return true;
        }

        // Réinitialiser le compteur si nécessaire
        $this->resetDailyQuotaIfNeeded($user);

        // Vérifier la limite quotidienne
        return $user->daily_searches_count < config('app.daily_search_limit', 5);
    }

    /**
     * Consommer un quota de recherche
     */
    public function consumeSearchQuota(User $user): bool
    {
        // Les admins et les utilisateurs premium n'ont pas de limites
        if ($user->isAdmin() || $user->isPremium()) {
            Log::info('Recherche effectuée par utilisateur avec accès illimité', [
                'user_id' => $user->id,
                'type' => $user->isAdmin() ? 'admin' : 'premium'
            ]);
            return true;
        }

        // Vérifier si l'utilisateur peut encore rechercher
        if (!$this->canUserSearch($user)) {
            Log::warning('Tentative de recherche au-delà de la limite quotidienne', [
                'user_id' => $user->id,
                'current_count' => $user->daily_searches_count,
                'limit' => config('app.daily_search_limit', 5)
            ]);
            return false;
        }

        // Incrémenter le compteur
        try {
            $user->incrementSearchCount();
            
            Log::info('Quota de recherche consommé', [
                'user_id' => $user->id,
                'new_count' => $user->daily_searches_count,
                'remaining' => $this->getRemainingSearches($user)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la consommation du quota', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtenir le nombre de recherches restantes
     */
    public function getRemainingSearches(User $user): int
    {
        if ($user->isAdmin() || $user->isPremium()) {
            return -1; // Illimité
        }

        $this->resetDailyQuotaIfNeeded($user);
        
        $limit = config('app.daily_search_limit', 5);
        return max(0, $limit - $user->daily_searches_count);
    }

    /**
     * Obtenir les informations de quota pour l'utilisateur
     */
    public function getQuotaInfo(User $user): array
    {
        if ($user->isAdmin() || $user->isPremium()) {
            return [
                'is_premium' => $user->isPremium(),
                'is_admin' => $user->isAdmin(),
                'unlimited' => true,
                'remaining' => -1,
                'limit' => -1,
                'used' => 0,
                'reset_time' => null,
            ];
        }

        $this->resetDailyQuotaIfNeeded($user);

        $limit = config('app.daily_search_limit', 5);
        $used = $user->daily_searches_count;
        $remaining = max(0, $limit - $used);

        return [
            'is_premium' => false,
            'unlimited' => false,
            'remaining' => $remaining,
            'limit' => $limit,
            'used' => $used,
            'reset_time' => $user->daily_searches_reset_at?->addDay()->startOfDay(),
            'can_search' => $remaining > 0,
        ];
    }

    /**
     * Réinitialiser tous les quotas quotidiens (commande CRON)
     */
    public function resetAllDailyQuotas(): int
    {
        $resetCount = 0;

        try {
            // Réinitialiser pour tous les utilisateurs gratuits qui ont un ancien reset_at
            $updated = User::free()
                ->where(function($query) {
                    $query->whereNull('daily_searches_reset_at')
                          ->orWhere('daily_searches_reset_at', '<', now()->startOfDay());
                })
                ->update([
                    'daily_searches_count' => 0,
                    'daily_searches_reset_at' => now(),
                ]);

            $resetCount = $updated;

            Log::info('Réinitialisation quotidienne des quotas de recherche', [
                'users_reset' => $resetCount,
                'reset_time' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la réinitialisation des quotas quotidiens', [
                'error' => $e->getMessage()
            ]);
        }

        return $resetCount;
    }

    /**
     * Obtenir les statistiques d'utilisation des recherches
     */
    public function getSearchUsageStats(): array
    {
        return [
            'total_users' => User::count(),
            'premium_users' => User::premium()->count(),
            'free_users' => User::free()->count(),
            'free_users_with_searches_today' => User::free()
                ->where('daily_searches_count', '>', 0)
                ->whereDate('daily_searches_reset_at', today())
                ->count(),
            'free_users_at_limit' => User::free()
                ->where('daily_searches_count', '>=', config('app.daily_search_limit', 5))
                ->whereDate('daily_searches_reset_at', today())
                ->count(),
            'total_searches_today' => User::free()
                ->whereDate('daily_searches_reset_at', today())
                ->sum('daily_searches_count'),
            'average_searches_per_free_user' => User::free()
                ->whereDate('daily_searches_reset_at', today())
                ->avg('daily_searches_count') ?? 0,
        ];
    }

    /**
     * Forcer la réinitialisation du quota pour un utilisateur spécifique
     */
    public function resetUserQuota(User $user): bool
    {
        try {
            $user->update([
                'daily_searches_count' => 0,
                'daily_searches_reset_at' => now(),
            ]);

            Log::info('Quota utilisateur réinitialisé manuellement', [
                'user_id' => $user->id,
                'reset_by' => 'manual',
                'reset_time' => now()->toDateTimeString()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la réinitialisation manuelle du quota', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Réinitialiser le quota quotidien si nécessaire pour un utilisateur
     */
    private function resetDailyQuotaIfNeeded(User $user): void
    {
        if (!$user->isAdmin() && !$user->isPremium()) {
            $user->resetDailySearchesIfNeeded();
        }
    }
}