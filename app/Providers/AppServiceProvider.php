<?php

namespace App\Providers;

use App\Services\Auth\CurrentUserContext;
use App\Services\Branches\BranchCatalog;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\Discipleship\DiscipleshipReadCache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BranchCatalog::class);
        $this->app->singleton(DiscipleshipReadCache::class);
        $this->app->scoped(CurrentUserContext::class);
        $this->app->scoped(CurrentDiscipleshipScope::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
