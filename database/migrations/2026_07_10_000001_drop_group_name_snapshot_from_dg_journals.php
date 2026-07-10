<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'jurnal_temu_dg',
        'jurnal_umpan_balik',
        'discipleship_meeting_reports',
        'discipleship_feedbacks',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'group_name_snapshot')) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropColumn('group_name_snapshot');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'group_name_snapshot')) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table): void {
                $table->string('group_name_snapshot')->nullable();
            });
        }
    }
};
