<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $indexes = [
        'discipleship_meeting_reports' => [
            'dmr_branch_group_date_id_idx' => ['branch_id', 'discipleship_group_id', 'meeting_date', 'id'],
        ],
        'msk_participants' => [
            'msk_branch_status_batch_id_idx' => ['branch_id', 'status', 'batch_month', 'id'],
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

    private function missingColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return true;
            }
        }

        return false;
    }

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
