<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PremiumMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }
            
            return redirect()->route('login');
        }

        // Vérifier si l'utilisateur est premium
        if (!$user->isPremium()) {
            $message = 'Cette fonctionnalité est réservée aux utilisateurs Premium. Passez à Premium pour accéder à toutes les fonctionnalités.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error_code' => 'PREMIUM_REQUIRED',
                    'data' => [
                        'current_plan' => 'free',
                        'upgrade_message' => 'Passez à Premium pour accéder à vos prospects, ajouter des notes et profiter de recherches illimitées.',
                        'features_locked' => [
                            'prospects_management' => 'Gestion des prospects',
                            'notes_system' => 'Système de notes',
                            'unlimited_searches' => 'Recherches illimitées',
                            'advanced_filters' => 'Filtres avancés'
                        ]
                    ]
                ], 403); // Forbidden
            }
            
            // Pour les requêtes web, rediriger vers une page d'upgrade
            return redirect('/upgrade')->with('message', $message);
        }

        return $next($request);
    }
}
