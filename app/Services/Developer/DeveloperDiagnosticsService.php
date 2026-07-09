<?php

namespace App\Services\Developer;

use App\Models\Branch;
use App\Models\User;
use Throwable;

class DeveloperDiagnosticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'counts' => $this->counts(),
            'runtime' => $this->runtime(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'users' => $this->safeCount(User::class),
            'active_users' => $this->safeUserCount(true),
            'active_developers' => $this->safeActiveDeveloperCount(),
            'branches' => $this->safeActiveDiscipleshipBranchCount(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function runtime(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => (string) config('app.env', ''),
            'app_debug' => config('app.debug') ? 'true' : 'false',
            'app_timezone' => app_config_value('app_timezone', (string) config('app.timezone', 'Asia/Jakarta')),
            'db_connection' => (string) config('database.default', ''),
        ];
    }

    /**
     * @param  class-string  $model
     */
    private function safeCount(string $model): int
    {
        try {
            return $model::query()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeUserCount(bool $active): int
    {
        try {
            return User::query()->where('is_active', $active)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDeveloperCount(): int
    {
        try {
            return User::query()
                ->where('access_scope', 'developer')
                ->where('is_active', true)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDiscipleshipBranchCount(): int
    {
        try {
            return Branch::query()
                ->where('is_active', true)
                ->where('label', '!=', 'Pusat')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
