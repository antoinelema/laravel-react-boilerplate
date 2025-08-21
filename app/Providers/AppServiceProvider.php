<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Vite;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\__Domain__\Data\User\Collection;
use App\__Infrastructure__\Persistence\User\UserRepository;
use App\__Domain__\Data\Prospect\Collection as ProspectCollection;
use App\__Domain__\Data\ProspectNote\Collection as ProspectNoteCollection;
use App\__Domain__\Data\ProspectSearch\Collection as ProspectSearchCollection;
use App\__Infrastructure__\Persistence\Prospect\ProspectRepository;
use App\__Infrastructure__\Persistence\ProspectNote\ProspectNoteRepository;
use App\__Infrastructure__\Persistence\ProspectSearch\ProspectSearchRepository;
use App\__Infrastructure__\Services\External\GoogleMapsService;
use App\__Infrastructure__\Services\External\NominatimService;
use App\__Infrastructure__\Services\External\ClearbitService;
use App\__Infrastructure__\Services\External\HunterService;
use App\__Infrastructure__\Services\Cache\ProspectSearchCacheService;
use App\__Infrastructure__\Services\Aggregation\SearchAggregatorService;
use App\__Infrastructure__\Services\Aggregation\ResultMergerService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // UserRepository binding for clean architecture
        $this->app->bind(
            Collection::class,
            UserRepository::class
        );

        // Prospect repositories bindings
        $this->app->bind(ProspectCollection::class, ProspectRepository::class);
        $this->app->bind(ProspectNoteCollection::class, ProspectNoteRepository::class);
        $this->app->bind(ProspectSearchCollection::class, ProspectSearchRepository::class);

        // External services bindings
        $this->app->singleton(GoogleMapsService::class);
        $this->app->singleton(NominatimService::class);
        $this->app->singleton(ClearbitService::class);
        $this->app->singleton(HunterService::class);

        // Cache service binding
        $this->app->singleton(ProspectSearchCacheService::class);

        // Aggregation services bindings
        $this->app->singleton(ResultMergerService::class);
        $this->app->singleton(SearchAggregatorService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.vite_disable')) {
            app()->instance(Vite::class, new class {
                public function __invoke():string { return ''; }
                public function asset():string { return ''; }
            });
        }

        Inertia::share([
            'auth' => [
                'user' => fn () => app()->bound('auth') && request()->hasSession() ? Auth::user() : null,
            ],
        ]);
    }
}
