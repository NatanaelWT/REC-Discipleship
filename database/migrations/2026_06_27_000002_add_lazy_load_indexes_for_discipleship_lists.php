<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{table:string,index:string,columns:array<int,string>}> */
    private array $indexes = [
        [
            'table' => 'discipleship_groups',
            'index' => 'dg_branch_status_name_id_lazy_idx',
            'columns' => ['branch_id', 'status', 'name', 'id'],
        ],
        [
            'table' => 'discipleship_group_people',
            'index' => 'dgp_branch_group_person_role_status_lazy_idx',
            'columns' => ['branch_id', 'discipleship_group_id', 'person_id', 'role', 'status'],
        ],
        [
            'table' => 'msk_participants',
            'index' => 'msk_branch_batch_name_id_lazy_idx',
            'columns' => ['branch_id', 'batch_month', 'full_name', 'id'],
        ],
        [
            'table' => 'msk_participants',
            'index' => 'msk_branch_bridge_name_id_lazy_idx',
            'columns' => ['branch_id', 'journey_bridge_status', 'full_name', 'id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $definition) {
            if (! $this->canAddIndex($definition)) {
                continue;
            }

            Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
                $table->index($definition['columns'], $definition['index']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $definition) {
            if (! Schema::hasTable($definition['table']) || ! Schema::hasIndex($definition['table'], $definition['index'])) {
                continue;
            }

            Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
                $table->dropIndex($definition['index']);
            });
        }
    }

    /** @param array{table:string,index:string,columns:array<int,string>} $definition */
    private function canAddIndex(array $definition): bool
    {
        if (! Schema::hasTable($definition['table']) || Schema::hasIndex($definition['table'], $definition['index'])) {
            return false;
        }

        foreach ($definition['columns'] as $column) {
            if (! Schema::hasColumn($definition['table'], $column)) {
                return false;
            }
        }

        foreach (Schema::getIndexes($definition['table']) as $index) {
            if (($index['columns'] ?? []) === $definition['columns']) {
                return false;
            }
        }

        return true;
    }
};
