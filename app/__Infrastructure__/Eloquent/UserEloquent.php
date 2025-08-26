<?php

namespace App\__Infrastructure__\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class UserEloquent extends Model implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use HasFactory;
    use HasApiTokens;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'firstname',
        'email',
        'password',
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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
            'daily_searches_reset_at' => 'datetime',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has premium subscription
     */
    public function isPremium(): bool
    {
        return $this->subscription_type === 'premium' && 
               ($this->subscription_expires_at === null || $this->subscription_expires_at->isFuture());
    }

    /**
     * Check if user has free subscription
     */
    public function isFree(): bool
    {
        return !$this->isPremium();
    }

    /**
     * Upgrade user to premium
     */
    public function upgradeToPremium(?Carbon $expiresAt = null): bool
    {
        return $this->update([
            'subscription_type' => 'premium',
            'subscription_expires_at' => $expiresAt
        ]);
    }

    /**
     * Downgrade user to free
     */
    public function downgradeTo(): void
    {
        $this->update([
            'subscription_type' => 'free',
            'subscription_expires_at' => null,
            'daily_searches_count' => 0,
            'daily_searches_reset_at' => null
        ]);
    }

    /**
     * Downgrade user to free (alias for controller)
     */
    public function downgradeToFree(): bool
    {
        $this->downgradeTo();
        return true;
    }

    /**
     * Check if user can make a search
     */
    public function canMakeSearch(): bool
    {
        if ($this->isPremium()) {
            return true;
        }

        // Reset daily count if needed
        $this->resetDailySearchesIfNeeded();

        return $this->daily_searches_count < $this->getDailySearchLimit();
    }

    /**
     * Increment search count for free users
     */
    public function incrementSearchCount(): void
    {
        if ($this->isFree()) {
            $this->resetDailySearchesIfNeeded();
            
            $this->increment('daily_searches_count');
            
            if ($this->daily_searches_reset_at === null) {
                $this->update([
                    'daily_searches_reset_at' => now()->addDay()
                ]);
            }
        }
    }

    /**
     * Reset daily searches count if needed
     */
    public function resetDailySearchesIfNeeded(): void
    {
        if ($this->daily_searches_reset_at && $this->daily_searches_reset_at->isPast()) {
            $this->update([
                'daily_searches_count' => 0,
                'daily_searches_reset_at' => now()->addDay()
            ]);
        }
    }

    /**
     * Get daily search limit for user
     */
    public function getDailySearchLimit(): int
    {
        return $this->isPremium() ? -1 : 5; // -1 = unlimited for premium
    }

    /**
     * Reset user's daily search quota (admin function)
     */
    public function resetSearchQuota(): void
    {
        $this->update([
            'daily_searches_count' => 0,
            'daily_searches_reset_at' => now()->addDay()
        ]);
    }

    /**
     * Get remaining searches for the day
     */
    public function getRemainingSearches(): int
    {
        if ($this->isPremium()) {
            return -1; // unlimited
        }

        $this->resetDailySearchesIfNeeded();
        
        return max(0, $this->getDailySearchLimit() - $this->daily_searches_count);
    }

    /**
     * Scope for premium users
     */
    public function scopePremium($query)
    {
        return $query->where('subscription_type', 'premium')
                    ->where(function($q) {
                        $q->whereNull('subscription_expires_at')
                          ->orWhere('subscription_expires_at', '>', now());
                    });
    }

    /**
     * Scope for free users
     */
    public function scopeFree($query)
    {
        return $query->where(function($q) {
            $q->where('subscription_type', 'free')
              ->orWhere('subscription_expires_at', '<=', now());
        });
    }

    /**
     * Scope for active users (not expired premium)
     */
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->where('subscription_type', 'free')
              ->orWhere(function($subQ) {
                  $subQ->where('subscription_type', 'premium')
                       ->where(function($expQ) {
                           $expQ->whereNull('subscription_expires_at')
                                ->orWhere('subscription_expires_at', '>', now());
                       });
              });
        });
    }

    /**
     * Scope for admin users
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Relation with prospects (if exists)
     */
    public function prospects()
    {
        return $this->hasMany(\App\__Infrastructure__\Eloquent\ProspectEloquent::class, 'user_id');
    }

}
