<?php

namespace App\Providers;

use App\Services\Branches\BranchCatalog;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
