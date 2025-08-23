<?php

namespace App\Http\Middleware;

use App\__Infrastructure__\Services\User\SearchQuotaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SearchLimitMiddleware
{
    private SearchQuotaService $searchQuotaService;

    public function __construct(SearchQuotaService $searchQuotaService)
    {
        $this->searchQuotaService = $searchQuotaService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Vérifier si l'utilisateur peut effectuer une recherche
        if (!$this->searchQuotaService->canUserSearch($user)) {
            $quotaInfo = $this->searchQuotaService->getQuotaInfo($user);
            
            return response()->json([
                'success' => false,
                'message' => 'Limite quotidienne de recherches atteinte. Passez à Premium pour des recherches illimitées.',
                'error_code' => 'SEARCH_LIMIT_EXCEEDED',
                'data' => [
                    'quota_info' => $quotaInfo,
                    'upgrade_message' => 'Passez à Premium pour accéder à des recherches illimitées et plus de fonctionnalités.'
                ]
            ], 429); // Too Many Requests
        }

        return $next($request);
    }
}
