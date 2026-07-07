<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropWebsiteSessionsMigrationTest extends TestCase
{
    public function test_migration_drops_session_table_and_activity_session_id(): void
    {
        $this->createTables();

        $migration = require database_path('migrations/2026_07_07_000003_drop_website_sessions_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('aktivitas'));
        $this->assertFalse(Schema::hasColumn('aktivitas', 'session_id'));
        $this->assertFalse(Schema::hasTable('sesi'));
        $this->assertFalse(Schema::hasTable('website_sessions'));
        $this->assertDatabaseHas('aktivitas', ['id' => '01KDROP0000000000000000001', 'path' => '/drop-test']);

        $migration->down();

        $this->assertTrue(Schema::hasColumn('aktivitas', 'session_id'));
        $this->assertTrue(Schema::hasTable('sesi'));
        $this->assertSame(0, DB::table('sesi')->count());
        $this->assertNull(DB::table('aktivitas')->where('id', '01KDROP0000000000000000001')->value('session_id'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('aktivitas');
        Schema::dropIfExists('sesi');
        Schema::dropIfExists('website_sessions');

        parent::tearDown();
    }

    private function createTables(): void
    {
        Schema::create('aktivitas', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('session_id')->nullable()->index();
            $table->char('visitor_hash', 64)->nullable();
            $table->text('path');
            $table->dateTime('occurred_at', 6)->nullable();
            $table->index(['session_id', 'occurred_at']);
        });

        DB::table('aktivitas')->insert([
            'id' => '01KDROP0000000000000000001',
            'session_id' => '01KDROP0000000000000000002',
            'visitor_hash' => str_repeat('a', 64),
            'path' => '/drop-test',
            'occurred_at' => '2026-07-07 10:00:00.000000',
        ]);

        Schema::create('sesi', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });
        Schema::create('website_sessions', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });
    }
}
