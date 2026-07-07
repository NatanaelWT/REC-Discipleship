<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aktivitas') && Schema::hasColumn('aktivitas', 'session_id')) {
            $this->dropIndexIfExists('aktivitas', 'aktivitas_session_id_occurred_at_index');
            $this->dropIndexIfExists('aktivitas', 'aktivitas_session_id_index');

            Schema::table('aktivitas', static function (Blueprint $table): void {
                $table->dropColumn('session_id');
            });
        }

        Schema::disableForeignKeyConstraints();
        try {
            Schema::dropIfExists('sesi');
            Schema::dropIfExists('website_sessions');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('aktivitas') && ! Schema::hasColumn('aktivitas', 'session_id')) {
            Schema::table('aktivitas', static function (Blueprint $table): void {
                $table->ulid('session_id')->nullable()->index();
            });

            if (Schema::hasColumn('aktivitas', 'occurred_at')) {
                $this->addIndexIfMissing('aktivitas', ['session_id', 'occurred_at'], 'aktivitas_session_id_occurred_at_index');
            }
        }

        if (! Schema::hasTable('sesi')) {
            Schema::create('sesi', static function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->char('visitor_hash', 64)->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('username', 120)->nullable();
                $table->string('identity_source', 30);
                $table->dateTime('started_at', 6)->index();
                $table->dateTime('last_seen_at', 6)->index();
                $table->text('landing_path');
                $table->text('exit_path');
                $table->unsignedInteger('page_views')->default(1);

                $table->index(['visitor_hash', 'last_seen_at']);
                $table->index(['user_id', 'last_seen_at']);
            });
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, static function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        } catch (Throwable) {
            // The index can be absent on partially migrated databases.
        }
    }

    /** @param array<int, string> $columns */
    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        try {
            Schema::table($tableName, static function (Blueprint $table) use ($columns, $indexName): void {
                $table->index($columns, $indexName);
            });
        } catch (Throwable) {
            // The index can already exist after a partial rollback.
        }
    }
};
