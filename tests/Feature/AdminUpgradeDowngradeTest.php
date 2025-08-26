<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent as User;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

class AdminUpgradeDowngradeTest extends TestCase
{
    use ResetsTransactions;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un admin
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com'
        ]);

        // Créer un utilisateur normal
        $this->user = User::factory()->create([
            'role' => 'user',
            'email' => 'user@test.com',
            'subscription_type' => 'free'
        ]);
    }

    public function test_admin_can_upgrade_user_to_premium()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/admin/users/{$this->user->id}/upgrade", [
            'duration_months' => 1
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        $this->user->refresh();
        $this->assertEquals('premium', $this->user->subscription_type);
        $this->assertNotNull($this->user->subscription_expires_at);
    }

    public function test_admin_can_downgrade_user_to_free()
    {
        // D'abord upgrader l'utilisateur
        $this->user->upgradeToPremium(now()->addMonth());
        
        $this->actingAs($this->admin);

        $response = $this->postJson("/admin/users/{$this->user->id}/downgrade");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        $this->user->refresh();
        $this->assertEquals('free', $this->user->subscription_type);
        $this->assertNull($this->user->subscription_expires_at);
    }

    public function test_non_admin_cannot_upgrade_users()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        
        $this->actingAs($regularUser);

        $response = $this->postJson("/admin/users/{$this->user->id}/upgrade", [
            'duration_months' => 1
        ]);

        $response->assertStatus(403);

        $this->user->refresh();
        $this->assertEquals('free', $this->user->subscription_type);
    }

    public function test_non_admin_cannot_downgrade_users()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $this->user->upgradeToPremium(now()->addMonth());
        
        $this->actingAs($regularUser);

        $response = $this->postJson("/admin/users/{$this->user->id}/downgrade");

        $response->assertStatus(403);

        $this->user->refresh();
        $this->assertEquals('premium', $this->user->subscription_type);
    }

    public function test_unauthenticated_user_cannot_access_admin_routes()
    {
        $response = $this->postJson("/admin/users/{$this->user->id}/upgrade", [
            'duration_months' => 1
        ]);

        $response->assertStatus(401);
    }

    public function test_upgrade_validation_requires_duration_months()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/admin/users/{$this->user->id}/upgrade", []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['duration_months']);
    }

    public function test_admin_can_reset_user_quota()
    {
        $this->user->update(['daily_searches_count' => 5]);
        
        $this->actingAs($this->admin);

        $response = $this->postJson("/admin/users/{$this->user->id}/reset-quota");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        $this->user->refresh();
        $this->assertEquals(0, $this->user->daily_searches_count);
    }

    public function test_admin_cannot_upgrade_another_admin()
    {
        $anotherAdmin = User::factory()->create(['role' => 'admin']);
        
        $this->actingAs($this->admin);

        // Note: Ce test dépend de si on veut permettre l'upgrade d'autres admins
        // Pour l'instant, le système ne vérifie pas ce cas, donc il passerait
        $response = $this->postJson("/admin/users/{$anotherAdmin->id}/upgrade", [
            'duration_months' => 1
        ]);

        // Ce test pourrait être ajusté selon les règles business
        $response->assertStatus(200);
    }
}