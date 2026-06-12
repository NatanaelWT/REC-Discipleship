<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        App\Console\Commands\MigrateDifficultQuestionsToLaravelTable::class,
        App\Console\Commands\MigrateMemberFeedbackJournalsToLaravelTables::class,
        App\Console\Commands\MigrateDiscipleshipTargetsToLaravelTable::class,
        App\Console\Commands\MigrateWorshipServiceSchedulesToLaravelTables::class,
        App\Console\Commands\MigratePublicMaterialsToLaravelTables::class,
        App\Console\Commands\MigrateDgMeetingReportsToLaravelTables::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'rec_admin_session',
        ]);

        $middleware->validateCsrfTokens(except: [
            '/',
            'index.php',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
