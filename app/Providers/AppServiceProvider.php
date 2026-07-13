<?php

namespace App\Providers;

use App\Services\AppConfig\AppConfigService;
use App\Services\Auth\CurrentUserContext;
use App\Services\Branches\BranchCatalog;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\Discipleship\DiscipleshipReadCache;
use App\Services\Mutation\MutationLifecycle;
use App\Services\Performance\RequestPerformanceMonitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BranchCatalog::class);
        $this->app->scoped(DiscipleshipReadCache::class);
        $this->app->scoped(CurrentUserContext::class);
        $this->app->scoped(CurrentDiscipleshipScope::class);
        $this->app->scoped(MutationLifecycle::class);
        $this->app->singleton(RequestPerformanceMonitor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $timezone = AppConfigService::runtimeValues()['app_timezone'] ?? 'Asia/Jakarta';
        if (! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'Asia/Jakarta';
        }
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        if ((bool) config('performance.enabled') || $this->app->environment(['local', 'staging'])) {
            $monitor = $this->app->make(RequestPerformanceMonitor::class);
            DB::listen($monitor->record(...));
        }
    }
}
