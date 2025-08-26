<?php

namespace Tests\Unit;

use App\Http\Controllers\AdminController;
use App\__Infrastructure__\Persistence\Eloquent\User;
use App\__Infrastructure__\Services\User\UserSubscriptionService;
use App\__Infrastructure__\Services\User\SearchQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Mockery;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private AdminController $controller;
    private UserSubscriptionService $subscriptionService;
    private SearchQuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = new UserSubscriptionService();
        $this->quotaService = new SearchQuotaService();
        
        $this->controller = new AdminController(
            $this->subscriptionService,
            $this->quotaService
        );
    }

    public function test_upgrade_user_returns_success_response()
    {
        $user = User::factory()->create(['subscription_type' => 'free']);
        
        $request = new Request(['duration_months' => 1]);

        $response = $this->controller->upgradeUser($request, $user);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('upgradé vers premium', $data['message']);
        
        $user->refresh();
        $this->assertEquals('premium', $user->subscription_type);
    }

    public function test_downgrade_user_returns_success_response()
    {
        $user = User::factory()->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->addMonth()
        ]);

        $response = $this->controller->downgradeUser($user);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('rétrogradé vers gratuit', $data['message']);
        
        $user->refresh();
        $this->assertEquals('free', $user->subscription_type);
    }

    public function test_reset_user_quota_returns_success_response()
    {
        $user = User::factory()->create(['daily_searches_count' => 5]);

        $response = $this->controller->resetUserQuota($user);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('Quota de recherche réinitialisé', $data['message']);
        
        $user->refresh();
        $this->assertEquals(0, $user->daily_searches_count);
    }

    public function test_user_details_returns_complete_user_data()
    {
        $user = User::factory()->create();

        $response = $this->controller->userDetails($user);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('user', $data['data']);
        $this->assertArrayHasKey('subscriptions', $data['data']);
        $this->assertArrayHasKey('prospects_count', $data['data']);
        
        $this->assertEquals($user->id, $data['data']['user']['id']);
        $this->assertEquals($user->email, $data['data']['user']['email']);
    }

    public function test_stats_returns_comprehensive_statistics()
    {
        // Créer des utilisateurs de test
        $admin = User::factory()->create(['role' => 'admin']);
        $premiumUsers = User::factory()->count(3)->create(['subscription_type' => 'premium']);
        $freeUsers = User::factory()->count(2)->create(['subscription_type' => 'free', 'role' => 'user']);

        $response = $this->controller->stats();

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('users', $data['data']);
        $this->assertArrayHasKey('searches', $data['data']);
        $this->assertArrayHasKey('subscriptions', $data['data']);
        
        // Les factory créent des utilisateurs free par défaut, donc nous devons compter correctement
        $totalUsers = User::count();
        $premiumCount = User::where('subscription_type', 'premium')->count();
        $freeCount = User::where('subscription_type', 'free')->count();
        $adminCount = User::where('role', 'admin')->count();
        
        $this->assertEquals($totalUsers, $data['data']['users']['total']);
        $this->assertEquals($premiumCount, $data['data']['users']['premium']);
        $this->assertEquals($freeCount, $data['data']['users']['free']);
        $this->assertEquals($adminCount, $data['data']['users']['admin']);
    }
}