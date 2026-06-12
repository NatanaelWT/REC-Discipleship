<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $columnsByTable = [
        'rec_church_files' => ['uploaded_at_legacy', 'updated_at_legacy'],
        'rec_people_registry' => ['updated_at_legacy'],
        'rec_discipleship_groups' => ['updated_at_legacy'],
        'rec_dg_meeting_reports' => ['updated_at_legacy'],
        'rec_worship_penatalayan_schedules' => ['updated_at_legacy'],
        'rec_difficult_questions' => ['updated_at_legacy'],
    ];

    public function up(): void
    {
        foreach ($this->columnsByTable as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columnsToDrop = array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn($tableName, $column),
            ));

            if ($columnsToDrop === []) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columnsByTable as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($tableName, $columns): void {
                if (in_array('uploaded_at_legacy', $columns, true) && ! Schema::hasColumn($tableName, 'uploaded_at_legacy')) {
                    $table->string('uploaded_at_legacy', 80)->nullable();
                }

                if (in_array('updated_at_legacy', $columns, true) && ! Schema::hasColumn($tableName, 'updated_at_legacy')) {
                    $table->string('updated_at_legacy', 80)->nullable();
                }
            });
        }
    }
};
