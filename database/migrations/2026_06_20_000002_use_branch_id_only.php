<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $branchTables = [
        'users',
        'login_attempts',
        'difficult_questions',
        'discipleship_targets',
        'discipleship_people',
        'discipleship_groups',
        'discipleship_relationships',
        'discipleship_group_memberships',
        'discipleship_group_leaderships',
        'discipleship_group_multiplications',
        'discipleship_group_people',
        'msk_participants',
        'discipleship_meeting_reports',
        'discipleship_member_feedback_journals',
        'discipleship_feedbacks',
        'church_files',
        'public_material_files',
        'worship_service_schedules',
        'worship_schedules',
    ];

    /** @var array<int, string> */
    private array $globalTables = [
        'public_material_files',
        'worship_service_schedules',
        'worship_schedules',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        $this->ensureEveryCodeHasABranch();
        $this->backfillBranchIds();
        $this->removeLegacyCentralBranch();
        $this->ensureBranchForeignKeys();
        $this->replaceBranchCodeIndexes();

        foreach ($this->branchTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_code')) {
                Schema::table($table, static function (Blueprint $table): void {
                    $table->dropColumn('branch_code');
                });
            }
        }

        foreach ($this->globalTables as $table) {
            $this->dropBranchId($table);
        }

        $this->dropBranchCatalogColumns();
    }

    public function down(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', static function (Blueprint $table): void {
            if (! Schema::hasColumn('branches', 'code')) {
                $table->string('code', 40)->nullable();
            }
            if (! Schema::hasColumn('branches', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0);
            }
        });

        foreach ($this->globalTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            });
        }

        foreach (DB::table('branches')->orderBy('id')->get(['id', 'label']) as $position => $branch) {
            DB::table('branches')->where('id', $branch->id)->update([
                'code' => Str::slug((string) $branch->label),
                'sort_order' => $position,
            ]);
        }

        Schema::table('branches', static function (Blueprint $table): void {
            $table->unique('code', 'branches_code_unique');
        });

        $codes = DB::table('branches')->pluck('code', 'id');
        foreach ($this->branchTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            if (! Schema::hasColumn($table, 'branch_code')) {
                Schema::table($table, static function (Blueprint $table): void {
                    $table->string('branch_code', 40)->nullable()->index();
                });
            }

            foreach ($codes as $branchId => $code) {
                DB::table($table)->where('branch_id', $branchId)->update(['branch_code' => $code]);
            }
        }
    }

    private function ensureEveryCodeHasABranch(): void
    {
        if (! Schema::hasColumn('branches', 'code')) {
            return;
        }

        $known = DB::table('branches')->pluck('id', 'code')->all();
        foreach ($this->branchTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_code')) {
                continue;
            }

            foreach (DB::table($table)->whereNotNull('branch_code')->distinct()->pluck('branch_code') as $code) {
                $code = Str::slug((string) $code);
                if ($code === '' || isset($known[$code])) {
                    continue;
                }

                $attributes = [
                    'code' => $code,
                    'label' => Str::headline($code),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('branches', 'sort_order')) {
                    $attributes['sort_order'] = count($known);
                }
                if (Schema::hasColumn('branches', 'is_active')) {
                    $attributes['is_active'] = true;
                }

                $known[$code] = DB::table('branches')->insertGetId($attributes);
            }
        }
    }

    private function backfillBranchIds(): void
    {
        $branchIds = Schema::hasColumn('branches', 'code')
            ? DB::table('branches')->pluck('id', 'code')->all()
            : [];

        foreach ($this->branchTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_code')) {
                continue;
            }

            if (! Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, static function (Blueprint $table): void {
                    $table->foreignId('branch_id')->nullable();
                });
            }

            foreach ($branchIds as $code => $id) {
                DB::table($table)
                    ->where('branch_code', $code)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $id]);
            }
        }
    }

    private function replaceBranchCodeIndexes(): void
    {
        foreach ($this->branchTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_code')) {
                continue;
            }

            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if ($index['primary'] || ! in_array('branch_code', $index['columns'], true)) {
                    continue;
                }

                Schema::table($table, static function (Blueprint $blueprint) use ($index): void {
                    if ($index['unique']) {
                        $blueprint->dropUnique($index['name']);
                    } else {
                        $blueprint->dropIndex($index['name']);
                    }
                });

                $columns = array_values(array_unique(array_map(
                    static fn (string $column): string => $column === 'branch_code' ? 'branch_id' : $column,
                    $index['columns'],
                )));
                if ($columns === [] || $this->hasEquivalentIndex($table, $columns, $index['unique'])) {
                    continue;
                }

                $name = substr($table.'_'.implode('_', $columns).($index['unique'] ? '_unique' : '_index'), 0, 63);
                Schema::table($table, static function (Blueprint $blueprint) use ($columns, $index, $name): void {
                    if ($index['unique']) {
                        $blueprint->unique($columns, $name);
                    } else {
                        $blueprint->index($columns, $name);
                    }
                });
            }
        }
    }

    private function removeLegacyCentralBranch(): void
    {
        $query = DB::table('branches')->where('label', 'Pusat');
        if (Schema::hasColumn('branches', 'code')) {
            $query->orWhere('code', 'pusat');
        }

        $branchIds = $query->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        if ($branchIds === []) {
            return;
        }

        foreach ($this->branchTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                DB::table($table)->whereIn('branch_id', $branchIds)->update(['branch_id' => null]);
            }
        }

        DB::table('branches')->whereIn('id', $branchIds)->delete();
    }

    private function ensureBranchForeignKeys(): void
    {
        foreach (array_diff($this->branchTables, $this->globalTables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            $hasForeignKey = false;
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (in_array('branch_id', $foreignKey['columns'] ?? [], true)) {
                    $hasForeignKey = true;
                    break;
                }
            }
            if ($hasForeignKey) {
                continue;
            }

            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            });
        }
    }

    /** @param array<int, string> $columns */
    private function hasEquivalentIndex(string $table, array $columns, bool $unique): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns && $index['unique'] === $unique) {
                return true;
            }
        }

        return false;
    }

    private function dropBranchCatalogColumns(): void
    {
        if (Schema::hasColumn('branches', 'code')) {
            foreach (Schema::getIndexes('branches') as $index) {
                if ($index['primary'] || ! in_array('code', $index['columns'], true)) {
                    continue;
                }

                Schema::table('branches', static function (Blueprint $table) use ($index): void {
                    if ($index['unique']) {
                        $table->dropUnique($index['name']);
                    } else {
                        $table->dropIndex($index['name']);
                    }
                });
            }
        }

        if (! Schema::hasIndex('branches', ['label'], 'unique')) {
            Schema::table('branches', static function (Blueprint $table): void {
                $table->unique('label', 'branches_label_unique');
            });
        }

        Schema::table('branches', static function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['code', 'sort_order'],
                static fn (string $column): bool => Schema::hasColumn('branches', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function dropBranchId(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
            return;
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (! in_array('branch_id', $foreignKey['columns'] ?? [], true)) {
                continue;
            }

            $dropTarget = DB::getDriverName() === 'sqlite'
                ? ($foreignKey['columns'] ?? ['branch_id'])
                : $foreignKey['name'];

            Schema::table($table, static function (Blueprint $blueprint) use ($dropTarget): void {
                $blueprint->dropForeign($dropTarget);
            });
        }

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] || ! in_array('branch_id', $index['columns'], true)) {
                continue;
            }

            Schema::table($table, static function (Blueprint $blueprint) use ($index): void {
                if ($index['unique']) {
                    $blueprint->dropUnique($index['name']);
                } else {
                    $blueprint->dropIndex($index['name']);
                }
            });
        }

        Schema::table($table, static function (Blueprint $blueprint): void {
            $blueprint->dropColumn('branch_id');
        });
    }
};
