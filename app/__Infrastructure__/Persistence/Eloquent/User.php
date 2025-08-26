<?php

namespace App\__Infrastructure__\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Database\Factories\UserFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'name',
        'firstname',
        'email',
        'password',
        'google_id',
        'avatar',
        'role',
        'subscription_type',
        'subscription_expires_at',
        'daily_searches_count',
        'daily_searches_reset_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'subscription_expires_at' => 'datetime',
        'daily_searches_reset_at' => 'datetime',
    ];

    public function prospects()
    {
        return $this->hasMany(\App\__Infrastructure__\Eloquent\ProspectEloquent::class);
    }

    /**
     * Relation avec les abonnements
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\UserSubscription::class);
    }

    /**
     * Abonnement actuel
     */
    public function currentSubscription()
    {
        return $this->subscriptions()
                   ->where('status', 'active')
                   ->where('expires_at', '>', now())
                   ->latest();
    }

    /**
     * Vérifier si l'utilisateur est premium
     */
    public function isPremium(): bool
    {
        // Vérification basique sur le champ subscription_type
        if ($this->subscription_type === 'premium' && 
            $this->subscription_expires_at && 
            $this->subscription_expires_at->isFuture()) {
            return true;
        }

        // Vérification via l'abonnement actuel
        return $this->currentSubscription()->exists();
    }

    /**
     * Vérifier si l'utilisateur peut faire une recherche
     */
    public function canMakeSearch(): bool
    {
        // Les utilisateurs premium n'ont pas de limites
        if ($this->isPremium()) {
            return true;
        }

        // Réinitialiser le compteur si nécessaire
        $this->resetDailySearchesIfNeeded();

        // Vérifier la limite quotidienne pour les utilisateurs gratuits
        return $this->daily_searches_count < config('app.daily_search_limit', 5);
    }

    /**
     * Incrémenter le compteur de recherches
     */
    public function incrementSearchCount(): void
    {
        if (!$this->isPremium()) {
            $this->resetDailySearchesIfNeeded();
            $this->increment('daily_searches_count');
        }
    }

    /**
     * Obtenir le nombre de recherches restantes
     */
    public function getRemainingSearches(): int
    {
        if ($this->isPremium()) {
            return -1; // Illimité
        }

        $this->resetDailySearchesIfNeeded();
        $limit = config('app.daily_search_limit', 5);
        
        return max(0, $limit - $this->daily_searches_count);
    }

    /**
     * Réinitialiser le compteur quotidien si nécessaire
     */
    public function resetDailySearchesIfNeeded(): void
    {
        $now = now();
        
        // Si aucune date de reset n'existe ou si on est un nouveau jour
        if (!$this->daily_searches_reset_at || 
            !$this->daily_searches_reset_at->isSameDay($now)) {
            
            $this->update([
                'daily_searches_count' => 0,
                'daily_searches_reset_at' => $now,
            ]);
        }
    }

    /**
     * Upgrader vers premium
     */
    public function upgradeToPremium(\DateTimeInterface $expiresAt): bool
    {
        return $this->update([
            'subscription_type' => 'premium',
            'subscription_expires_at' => $expiresAt,
        ]);
    }

    /**
     * Downgrader vers gratuit
     */
    public function downgradeToFree(): bool
    {
        return $this->update([
            'subscription_type' => 'free',
            'subscription_expires_at' => null,
        ]);
    }

    /**
     * Scopes
     */
    public function scopePremium($query)
    {
        return $query->where('subscription_type', 'premium')
                    ->where(function($q) {
                        $q->whereNull('subscription_expires_at')
                          ->orWhere('subscription_expires_at', '>', now());
                    });
    }

    public function scopeFree($query)
    {
        return $query->where('subscription_type', 'free')
                    ->orWhere('subscription_expires_at', '<=', now());
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Scope pour les admins
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }
}