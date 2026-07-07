<?php

namespace App\Providers;

use App\Models\AppConfig;
use App\Models\Branch;
use App\Models\DifficultQuestion;
use App\Models\DiscipleshipFeedback;
use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipMeetingReport;
use App\Models\Person;
use App\Models\PublicMaterialFile;
use App\Models\User;
use App\Models\WorshipServiceSchedule;
use App\Observers\AuditableModelObserver;
use App\Services\Activity\ActivityContext;
use App\Services\AppConfig\AppConfigService;
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
        $this->app->scoped(ActivityContext::class);
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

        foreach ([
            AppConfig::class,
            Branch::class,
            DifficultQuestion::class,
            DiscipleshipFeedback::class,
            DiscipleshipGroup::class,
            DiscipleshipGroupPerson::class,
            DiscipleshipMeetingReport::class,
            Person::class,
            PublicMaterialFile::class,
            User::class,
            WorshipServiceSchedule::class,
        ] as $model) {
            $model::observe(AuditableModelObserver::class);
        }
    }
}
