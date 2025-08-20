<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Vite;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\__Domain__\Data\User\Collection;
use App\__Infrastructure__\Persistence\User\UserRepository;

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
