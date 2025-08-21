<?php

namespace App\Providers;

use App\__Domain__\Data\Prospect\Collection as ProspectCollection;
use App\__Domain__\Data\ProspectNote\Collection as ProspectNoteCollection;
use App\__Domain__\Data\ProspectSearch\Collection as ProspectSearchCollection;
use App\__Infrastructure__\Persistence\Prospect\ProspectRepository;
use App\__Infrastructure__\Persistence\ProspectNote\ProspectNoteRepository;
use App\__Infrastructure__\Persistence\ProspectSearch\ProspectSearchRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider pour les services Prospect
 */
class ProspectServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Liaison des interfaces aux implémentations concrètes
        $this->app->bind(ProspectCollection::class, ProspectRepository::class);
        $this->app->bind(ProspectNoteCollection::class, ProspectNoteRepository::class);
        $this->app->bind(ProspectSearchCollection::class, ProspectSearchRepository::class);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            ProspectCollection::class,
            ProspectNoteCollection::class,
            ProspectSearchCollection::class,
        ];
    }
}