<?php

use App\Http\Middleware\BootstrapRuntime;
use App\Http\Middleware\ExpireLegacyAnalyticsIdentity;
use App\Http\Middleware\HandleMaintenanceMode;
use App\Http\Middleware\InvalidateDiscipleshipReadCache;
use App\Http\Middleware\MeasureRequestPerformance;
use App\Http\Middleware\RejectLegacyPageQuery;
use App\Http\Middleware\RequireRecAuthentication;
use App\Http\Middleware\RequireRecPageAccess;
use App\Http\Middleware\WrapUnsafeRequestInTransaction;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [BootstrapRuntime::class],
            append: [ExpireLegacyAnalyticsIdentity::class, WrapUnsafeRequestInTransaction::class],
        );
        $middleware->append(MeasureRequestPerformance::class);
        $middleware->append(RejectLegacyPageQuery::class);
        $middleware->append(InvalidateDiscipleshipReadCache::class);
        $middleware->alias([
            'rec.maintenance' => HandleMaintenanceMode::class,
            'rec.auth' => RequireRecAuthentication::class,
            'rec.page' => RequireRecPageAccess::class,
        ]);

        $middleware->encryptCookies(except: [
            'rec_admin_session',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
