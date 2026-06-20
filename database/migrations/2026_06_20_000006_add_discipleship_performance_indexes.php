<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $indexes = [
        'discipleship_people' => [
            'dp_branch_status_id_idx' => ['branch_id', 'status', 'id'],
        ],
        'discipleship_groups' => [
            'dg_branch_status_stage_idx' => ['branch_id', 'status', 'current_stage'],
        ],
        'discipleship_relationships' => [
            'dr_branch_status_mentor_idx' => ['branch_id', 'status', 'mentor_person_id'],
            'dr_branch_status_disciple_idx' => ['branch_id', 'status', 'disciple_person_id'],
        ],
        'discipleship_group_people' => [
            'dgp_branch_group_role_status_idx' => ['branch_id', 'discipleship_group_id', 'role', 'status'],
            'dgp_branch_person_status_idx' => ['branch_id', 'person_id', 'status'],
        ],
        'discipleship_meeting_reports' => [
            'dmr_branch_date_id_idx' => ['branch_id', 'meeting_date', 'id'],
        ],
        'msk_participants' => [
            'msk_branch_batch_status_id_idx' => ['branch_id', 'batch_month', 'status', 'id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->missingColumns($table, $columns) || $this->hasEquivalentIndex($table, $columns)) {
                    continue;
                }

                Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, static function (Blueprint $blueprint) use ($name): void {
                        $blueprint->dropIndex($name);
                    });
                }
            }
        }
    }

    /** @param array<int, string> $columns */
    private function missingColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $columns */
    private function hasEquivalentIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }
};
