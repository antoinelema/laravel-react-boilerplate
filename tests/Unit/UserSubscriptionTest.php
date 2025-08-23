<?php

namespace Tests\Unit;

use App\__Infrastructure__\Persistence\Eloquent\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_is_free_by_default()
    {
        $user = User::factory()->create();
        
        $this->assertFalse($user->isPremium());
        $this->assertEquals('free', $user->subscription_type);
    }

    /** @test */
    public function user_can_be_upgraded_to_premium()
    {
        $user = User::factory()->create();
        $expiresAt = now()->addMonth();
        
        $user->upgradeToPremium($expiresAt);
        
        $this->assertTrue($user->isPremium());
        $this->assertEquals('premium', $user->subscription_type);
        $this->assertTrue($user->subscription_expires_at->isSameDay($expiresAt));
    }

    /** @test */
    public function user_premium_expires_correctly()
    {
        $user = User::factory()->create();
        $expiredDate = now()->subDay();
        
        $user->update([
            'subscription_type' => 'premium',
            'subscription_expires_at' => $expiredDate
        ]);
        
        $this->assertFalse($user->isPremium());
    }

    /** @test */
    public function user_can_make_search_when_premium()
    {
        $user = User::factory()->create();
        $user->upgradeToPremium(now()->addMonth());
        
        $this->assertTrue($user->canMakeSearch());
        $this->assertEquals(-1, $user->getRemainingSearches()); // Illimité
    }

    /** @test */
    public function free_user_has_daily_search_limit()
    {
        config(['app.daily_search_limit' => 5]);
        $user = User::factory()->create();
        
        $this->assertTrue($user->canMakeSearch());
        $this->assertEquals(5, $user->getRemainingSearches());
        
        // Simuler 3 recherches
        $user->update(['daily_searches_count' => 3]);
        $this->assertTrue($user->canMakeSearch());
        $this->assertEquals(2, $user->getRemainingSearches());
        
        // Atteindre la limite
        $user->update(['daily_searches_count' => 5]);
        $this->assertFalse($user->canMakeSearch());
        $this->assertEquals(0, $user->getRemainingSearches());
    }

    /** @test */
    public function search_count_increments_correctly_for_free_users()
    {
        $user = User::factory()->create();
        
        $this->assertEquals(0, $user->daily_searches_count);
        
        $user->incrementSearchCount();
        $this->assertEquals(1, $user->fresh()->daily_searches_count);
        
        $user->incrementSearchCount();
        $this->assertEquals(2, $user->fresh()->daily_searches_count);
    }

    /** @test */
    public function search_count_does_not_increment_for_premium_users()
    {
        $user = User::factory()->create();
        $user->upgradeToPremium(now()->addMonth());
        
        $this->assertEquals(0, $user->daily_searches_count);
        
        $user->incrementSearchCount();
        $this->assertEquals(0, $user->fresh()->daily_searches_count);
    }

    /** @test */
    public function daily_quota_resets_correctly()
    {
        $user = User::factory()->create();
        
        // Simuler des recherches d'hier
        $user->update([
            'daily_searches_count' => 5,
            'daily_searches_reset_at' => now()->subDay()
        ]);
        
        $this->assertEquals(5, $user->daily_searches_count);
        
        // Déclencher le reset
        $user->resetDailySearchesIfNeeded();
        
        $this->assertEquals(0, $user->fresh()->daily_searches_count);
        $this->assertTrue($user->fresh()->daily_searches_reset_at->isSameDay(now()));
    }

    /** @test */
    public function user_scopes_work_correctly()
    {
        // Créer des utilisateurs de test
        $freeUser = User::factory()->create(['subscription_type' => 'free']);
        $premiumUser = User::factory()->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->addMonth()
        ]);
        $expiredPremiumUser = User::factory()->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->subDay()
        ]);
        
        $this->assertEquals(2, User::free()->count()); // free + expired
        $this->assertEquals(1, User::premium()->count()); // only active premium
    }
}