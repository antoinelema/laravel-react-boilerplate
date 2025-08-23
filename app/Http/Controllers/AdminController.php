<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\__Infrastructure__\Persistence\Eloquent\User;
use App\__Infrastructure__\Services\User\UserSubscriptionService;
use App\__Infrastructure__\Services\User\SearchQuotaService;
use Carbon\Carbon;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function __construct(
        private UserSubscriptionService $subscriptionService,
        private SearchQuotaService $quotaService
    ) {}

    /**
     * Dashboard admin avec statistiques générales
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'premium_users' => User::premium()->count(),
            'free_users' => User::free()->count(),
            'admin_users' => User::admin()->count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_users_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)->count(),
            'daily_searches_today' => User::sum('daily_searches_count'),
            'expired_subscriptions' => User::where('subscription_type', 'premium')
                                         ->where('subscription_expires_at', '<', now())
                                         ->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'auth' => ['user' => auth()->user()],
            'stats' => $stats,
            'recent_users' => User::with('subscriptions')
                                 ->latest()
                                 ->limit(10)
                                 ->get()
                                 ->map(function ($user) {
                                     return [
                                         'id' => $user->id,
                                         'name' => $user->name . ' ' . $user->firstname,
                                         'email' => $user->email,
                                         'role' => $user->role,
                                         'subscription_type' => $user->subscription_type,
                                         'is_premium' => $user->isPremium(),
                                         'created_at' => $user->created_at->format('d/m/Y'),
                                         'daily_searches_count' => $user->daily_searches_count,
                                         'remaining_searches' => $user->getRemainingSearches(),
                                     ];
                                 })
        ]);
    }

    /**
     * Liste des utilisateurs avec filtres
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Filtres
        if ($request->filled('subscription_type')) {
            if ($request->subscription_type === 'premium') {
                $query->premium();
            } elseif ($request->subscription_type === 'free') {
                $query->free();
            }
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->with('subscriptions')
                      ->latest()
                      ->paginate(50)
                      ->through(function ($user) {
                          return [
                              'id' => $user->id,
                              'name' => $user->name . ' ' . $user->firstname,
                              'email' => $user->email,
                              'role' => $user->role,
                              'subscription_type' => $user->subscription_type,
                              'subscription_expires_at' => $user->subscription_expires_at?->format('d/m/Y H:i'),
                              'is_premium' => $user->isPremium(),
                              'created_at' => $user->created_at->format('d/m/Y'),
                              'daily_searches_count' => $user->daily_searches_count,
                              'remaining_searches' => $user->getRemainingSearches(),
                              'last_login' => $user->updated_at->format('d/m/Y H:i'),
                          ];
                      });

        return Inertia::render('Admin/Users', [
            'auth' => ['user' => auth()->user()],
            'users' => $users,
            'filters' => $request->only(['subscription_type', 'role', 'search']),
            'stats' => [
                'total' => User::count(),
                'premium' => User::premium()->count(),
                'free' => User::free()->count(),
                'admin' => User::admin()->count(),
            ]
        ]);
    }

    /**
     * Détails d'un utilisateur
     */
    public function userDetails(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'firstname' => $user->firstname,
                    'email' => $user->email,
                    'role' => $user->role,
                    'subscription_type' => $user->subscription_type,
                    'subscription_expires_at' => $user->subscription_expires_at,
                    'is_premium' => $user->isPremium(),
                    'daily_searches_count' => $user->daily_searches_count,
                    'daily_searches_reset_at' => $user->daily_searches_reset_at,
                    'remaining_searches' => $user->getRemainingSearches(),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'subscriptions' => $user->subscriptions()->latest()->get(),
                'prospects_count' => $user->prospects()->count(),
            ]
        ]);
    }

    /**
     * Statistiques globales API
     */
    public function stats()
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'premium' => User::premium()->count(),
                'free' => User::free()->count(),
                'admin' => User::admin()->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
                'new_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'new_month' => User::whereMonth('created_at', now()->month)->count(),
            ],
            'searches' => [
                'total_today' => User::sum('daily_searches_count'),
                'average_per_user' => round(User::where('daily_searches_count', '>', 0)->avg('daily_searches_count'), 2),
                'users_at_limit' => User::free()->where('daily_searches_count', '>=', config('app.daily_search_limit', 5))->count(),
            ],
            'subscriptions' => [
                'active_premium' => User::premium()->count(),
                'expired' => User::where('subscription_type', 'premium')
                                ->where('subscription_expires_at', '<', now())
                                ->count(),
                'expiring_soon' => User::where('subscription_type', 'premium')
                                      ->whereBetween('subscription_expires_at', [now(), now()->addDays(7)])
                                      ->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Upgrade manuel d'un utilisateur vers premium
     */
    public function upgradeUser(Request $request, User $user)
    {
        $request->validate([
            'duration_months' => 'required|integer|min:1|max:120',
        ]);

        $expiresAt = now()->addMonths($request->duration_months);
        
        if ($user->upgradeToPremium($expiresAt)) {
            return response()->json([
                'success' => true,
                'message' => "Utilisateur {$user->email} upgradé vers premium jusqu'au {$expiresAt->format('d/m/Y')}",
                'data' => [
                    'user' => $user->fresh(),
                    'expires_at' => $expiresAt->format('d/m/Y H:i')
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à niveau'
        ], 500);
    }

    /**
     * Downgrade d'un utilisateur vers gratuit
     */
    public function downgradeUser(User $user)
    {
        if ($user->downgradeToFree()) {
            return response()->json([
                'success' => true,
                'message' => "Utilisateur {$user->email} rétrogradé vers gratuit",
                'data' => [
                    'user' => $user->fresh()
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la rétrogradation'
        ], 500);
    }

    /**
     * Reset du quota de recherche d'un utilisateur
     */
    public function resetUserQuota(User $user)
    {
        $user->update([
            'daily_searches_count' => 0,
            'daily_searches_reset_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => "Quota de recherche réinitialisé pour {$user->email}",
            'data' => [
                'user' => $user->fresh()
            ]
        ]);
    }
}
