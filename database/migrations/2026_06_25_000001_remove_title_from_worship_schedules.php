<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['worship_schedules', 'worship_service_schedules'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'title')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('title');
            });
        }
    }

    public function down(): void
    {
        foreach (['worship_schedules', 'worship_service_schedules'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'title')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('title')->nullable();
            });
        }
    }
};
