<?php

namespace Tests\Feature;

use App\Services\Auth\LoginAttemptLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginAttemptLimiterEfficiencyTest extends TestCase
{
    public function test_pruning_expired_attempts_uses_one_delete_query(): void
    {
        Schema::dropIfExists('percobaan_login');
        Schema::create('percobaan_login', function (Blueprint $table): void {
            $table->id();
            $table->string('attempt_key', 120)->unique();
            $table->unsignedInteger('failed_attempt_count')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->timestamp('locked_until_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
        });

        $now = CarbonImmutable::parse('2026-07-13 12:00:00');
        $expiredAt = $now->subDays(3);
        $rows = [];
        for ($index = 0; $index < 100; $index++) {
            $rows[] = [
                'attempt_key' => hash('sha256', 'expired-'.$index),
                'failed_attempt_count' => 1,
                'window_started_at' => $expiredAt,
                'locked_until_at' => null,
                'last_attempted_at' => $expiredAt,
                'created_at' => $expiredAt,
                'updated_at' => $expiredAt,
            ];
        }
        $activeIp = '203.0.113.10';
        $rows[] = [
            'attempt_key' => hash('sha256', $activeIp),
            'failed_attempt_count' => 1,
            'window_started_at' => $now,
            'locked_until_at' => null,
            'last_attempted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        DB::table('percobaan_login')->insert($rows);

        $deleteQueries = 0;
        DB::listen(static function ($query) use (&$deleteQueries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'delete')) {
                $deleteQueries++;
            }
        });

        $this->assertSame(0, app(LoginAttemptLimiter::class)->waitSeconds($activeIp, $now));
        $this->assertSame(1, $deleteQueries);
        $this->assertSame(1, DB::table('percobaan_login')->count());
        $this->assertDatabaseHas('percobaan_login', ['attempt_key' => hash('sha256', $activeIp)]);
    }
}
