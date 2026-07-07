<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityTablesMergeMigrationTest extends TestCase
{
    public function test_activity_tables_are_merged_and_restored(): void
    {
        $this->dropTables();
        $this->createLegacyTables();

        $requestId = '01KXYZ00000000000000000001';
        DB::table('permintaan_aktivitas')->insert([
            'id' => $requestId,
            'actor_type' => 'anonymous',
            'method' => 'GET',
            'route_name' => 'public.merge-test',
            'path' => '/merge-test',
            'category' => 'request',
            'action' => 'request.page_view',
            'http_status' => 200,
            'outcome' => 'succeeded',
            'started_at' => '2026-07-07 10:00:00.000000',
            'completed_at' => '2026-07-07 10:00:00.100000',
        ]);
        DB::table('kunjungan_halaman')->insert([
            'request_id' => $requestId,
            'session_id' => '01KXYZ00000000000000000002',
            'visitor_hash' => str_repeat('a', 64),
            'identity_source' => 'anonymous_cookie',
            'actor_type' => 'anonymous',
            'segment' => 'publik',
            'route_name' => 'public.merge-test',
            'path' => '/merge-test',
            'language_code' => 'id-ID',
            'language_name' => 'Indonesia (id-ID)',
            'device_type' => 'desktop',
            'is_bot' => false,
            'is_prefetch' => false,
            'http_status' => 200,
            'response_ms' => 10.5,
            'occurred_at' => '2026-07-07 10:00:00.000000',
        ]);
        DB::table('peristiwa_aktivitas')->insert([
            [
                'request_id' => $requestId,
                'category' => 'data',
                'action' => 'people.updated',
                'subject_type' => 'orang',
                'subject_id' => '7',
                'subject_label' => 'Peserta Test',
                'description' => 'Update data',
                'changed_values' => json_encode(['name' => ['before' => 'A', 'after' => 'B']]),
                'occurred_at' => '2026-07-07 10:00:00.050000',
            ],
            [
                'request_id' => $requestId,
                'category' => 'request',
                'action' => 'request.validation_failed',
                'subject_type' => null,
                'subject_id' => null,
                'subject_label' => null,
                'description' => 'Validasi gagal',
                'changed_values' => null,
                'occurred_at' => '2026-07-07 10:00:00.060000',
            ],
        ]);

        $migration = require database_path('migrations/2026_07_07_000001_merge_activity_audit_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('aktivitas'));
        $this->assertFalse(Schema::hasTable('permintaan_aktivitas'));
        $this->assertFalse(Schema::hasTable('peristiwa_aktivitas'));
        $this->assertFalse(Schema::hasTable('kunjungan_halaman'));
        $this->assertSame(1, DB::table('aktivitas')->count());

        $activity = DB::table('aktivitas')->where('id', $requestId)->first();
        $this->assertNotNull($activity);
        $this->assertSame(1, (int) $activity->is_page_view);
        $this->assertSame(2, (int) $activity->events_count);
        $events = json_decode((string) $activity->event_entries, true);
        $this->assertSame(['people.updated', 'request.validation_failed'], array_column($events, 'action'));

        $migration->down();

        $this->assertFalse(Schema::hasTable('aktivitas'));
        $this->assertTrue(Schema::hasTable('permintaan_aktivitas'));
        $this->assertTrue(Schema::hasTable('peristiwa_aktivitas'));
        $this->assertTrue(Schema::hasTable('kunjungan_halaman'));
        $this->assertSame(1, DB::table('permintaan_aktivitas')->count());
        $this->assertSame(1, DB::table('kunjungan_halaman')->count());
        $this->assertSame(2, DB::table('peristiwa_aktivitas')->count());
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    private function createLegacyTables(): void
    {
        Schema::create('permintaan_aktivitas', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('actor_type')->default('anonymous');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_label')->nullable();
            $table->char('visitor_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('method', 12);
            $table->string('route_name')->nullable();
            $table->text('path');
            $table->string('category')->default('request');
            $table->string('action')->default('request');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('query_data')->nullable();
            $table->json('input_data')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome')->default('pending');
            $table->text('redirect_to')->nullable();
            $table->string('response_content_type')->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->decimal('duration_ms', 14, 3)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
        });
        Schema::create('peristiwa_aktivitas', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('request_id');
            $table->string('category');
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->text('description')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('changed_values')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);
        });
        Schema::create('kunjungan_halaman', static function (Blueprint $table): void {
            $table->ulid('request_id')->primary();
            $table->ulid('session_id');
            $table->char('visitor_hash', 64);
            $table->string('identity_source');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('actor_type');
            $table->string('segment');
            $table->string('route_name')->nullable();
            $table->text('path');
            $table->string('referer_host')->nullable();
            $table->string('language_code', 20)->nullable();
            $table->string('language_name', 100)->nullable();
            $table->string('device_type');
            $table->string('browser_name')->nullable();
            $table->string('os_name')->nullable();
            $table->boolean('is_bot');
            $table->boolean('is_prefetch');
            $table->unsignedSmallInteger('http_status');
            $table->decimal('response_ms', 14, 3)->nullable();
            $table->dateTime('occurred_at', 6);
        });
    }

    private function dropTables(): void
    {
        Schema::dropIfExists('aktivitas');
        Schema::dropIfExists('kunjungan_halaman');
        Schema::dropIfExists('peristiwa_aktivitas');
        Schema::dropIfExists('permintaan_aktivitas');
    }
}
