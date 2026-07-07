<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kelompok_dg')) {
            return;
        }

        $this->dropIndexIfExists('kelompok_dg', 'dg_branch_status_name_id_lazy_idx');
        $this->dropIndexIfExists('kelompok_dg', 'discipleship_groups_current_stage_index');
        $this->dropIndexIfExists('kelompok_dg', 'kelompok_dg_current_stage_index');

        if (! Schema::hasColumn('kelompok_dg', 'stage')) {
            if (Schema::hasColumn('kelompok_dg', 'current_stage')) {
                Schema::table('kelompok_dg', static function (Blueprint $table): void {
                    $table->renameColumn('current_stage', 'stage');
                });
            } else {
                Schema::table('kelompok_dg', static function (Blueprint $table): void {
                    $table->string('stage')->nullable()->after('status');
                });
            }
        }

        if (Schema::hasColumn('kelompok_dg', 'stage') && Schema::hasColumn('kelompok_dg', 'start_stage')) {
            DB::table('kelompok_dg')
                ->where(static function ($query): void {
                    $query->whereNull('stage')->orWhere('stage', '');
                })
                ->whereNotNull('start_stage')
                ->where('start_stage', '!=', '')
                ->update(['stage' => DB::raw('start_stage')]);
        }

        $this->dropColumnIfExists('kelompok_dg', 'name');
        $this->dropColumnIfExists('kelompok_dg', 'start_stage');

        $this->addIndexIfMissing('kelompok_dg', ['stage'], 'kelompok_dg_stage_index');
        $this->addIndexIfMissing('kelompok_dg', ['branch_id', 'status', 'stage', 'id'], 'dg_branch_status_stage_id_lazy_idx');
    }

    public function down(): void
    {
        if (! Schema::hasTable('kelompok_dg')) {
            return;
        }

        $this->dropIndexIfExists('kelompok_dg', 'kelompok_dg_stage_index');
        $this->dropIndexIfExists('kelompok_dg', 'dg_branch_status_stage_id_lazy_idx');

        if (Schema::hasColumn('kelompok_dg', 'stage') && ! Schema::hasColumn('kelompok_dg', 'current_stage')) {
            Schema::table('kelompok_dg', static function (Blueprint $table): void {
                $table->renameColumn('stage', 'current_stage');
            });
        } elseif (! Schema::hasColumn('kelompok_dg', 'current_stage')) {
            Schema::table('kelompok_dg', static function (Blueprint $table): void {
                $table->string('current_stage')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('kelompok_dg', 'name')) {
            Schema::table('kelompok_dg', static function (Blueprint $table): void {
                $table->string('name')->default('Kelompok')->after('branch_id');
            });
        }

        if (! Schema::hasColumn('kelompok_dg', 'start_stage')) {
            Schema::table('kelompok_dg', static function (Blueprint $table): void {
                $table->string('start_stage')->nullable()->after('status');
            });
        }

        if (Schema::hasColumn('kelompok_dg', 'start_stage') && Schema::hasColumn('kelompok_dg', 'current_stage')) {
            DB::table('kelompok_dg')
                ->where(static function ($query): void {
                    $query->whereNull('start_stage')->orWhere('start_stage', '');
                })
                ->whereNotNull('current_stage')
                ->where('current_stage', '!=', '')
                ->update(['start_stage' => DB::raw('current_stage')]);
        }

        $this->addIndexIfMissing('kelompok_dg', ['current_stage'], 'discipleship_groups_current_stage_index');
        $this->addIndexIfMissing('kelompok_dg', ['branch_id', 'status', 'name', 'id'], 'dg_branch_status_name_id_lazy_idx');
    }

    private function dropColumnIfExists(string $tableName, string $columnName): void
    {
        if (! Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, static function (Blueprint $table) use ($columnName): void {
            $table->dropColumn($columnName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, static function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    /** @param array<int, string> $columns */
    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['columns'] ?? []) === $columns) {
                return;
            }
        }

        Schema::table($tableName, static function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }
};
