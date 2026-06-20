<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeUserBranchNullable();
        $this->migrateUserRoles();
        $this->makeWorshipSchedulesGlobal();
        $this->removeCentralBranch();
    }

    public function down(): void
    {
        $this->restoreCentralBranch();
        $this->restoreLegacyUserRoles();
        $this->restoreWorshipScheduleBranchScope();

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_code')) {
            Schema::table('users', static function (Blueprint $table): void {
                $table->string('branch_code', 40)->default('kutisari')->nullable(false)->change();
                if (Schema::hasColumn('users', 'access_scope')) {
                    $table->string('access_scope', 80)->default('branch')->change();
                }
            });
        }
    }

    private function makeUserBranchNullable(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', static function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'branch_code')) {
                $table->string('branch_code', 40)->nullable()->default(null)->change();
            }
            if (Schema::hasColumn('users', 'access_scope')) {
                $table->string('access_scope', 80)->default('pemuridan_cabang')->change();
            }
        });
    }

    private function migrateUserRoles(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'access_scope')) {
            return;
        }

        DB::table('users')->whereIn('access_scope', ['branch', 'discipleship_branch'])->update([
            'access_scope' => 'pemuridan_cabang',
        ]);
        DB::table('users')->where('access_scope', 'central_discipleship_readonly')->update([
            'access_scope' => 'pemuridan_pusat',
        ]);
        DB::table('users')->where('access_scope', 'worship_only')->update([
            'access_scope' => 'pelayan',
        ]);
        DB::table('users')->where('username', 'keziaae')->update([
            'access_scope' => 'pelayan',
        ]);

        $branchUserUpdate = ['branch_code' => 'kutisari'];
        if (Schema::hasColumn('users', 'branch_id')) {
            $branchUserUpdate['branch_id'] = $this->branchId('kutisari');
        }
        DB::table('users')
            ->where('access_scope', 'pemuridan_cabang')
            ->where(static function ($query): void {
                $query->whereNull('branch_code')->orWhere('branch_code', '')->orWhere('branch_code', 'pusat');
            })
            ->update($branchUserUpdate);

        $withoutBranch = ['branch_code' => null];
        if (Schema::hasColumn('users', 'branch_id')) {
            $withoutBranch['branch_id'] = null;
        }
        DB::table('users')
            ->whereIn('access_scope', ['pemuridan_pusat', 'pelayan', 'developer'])
            ->update($withoutBranch);
    }

    private function makeWorshipSchedulesGlobal(): void
    {
        if (Schema::hasTable('worship_schedules')) {
            foreach (DB::table('worship_schedules')->select('month')->groupBy('month')->havingRaw('COUNT(*) > 1')->pluck('month') as $month) {
                $ids = DB::table('worship_schedules')
                    ->where('month', $month)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->pluck('id')
                    ->all();

                if (count($ids) > 1) {
                    DB::table('worship_schedules')->whereIn('id', array_slice($ids, 1))->delete();
                }
            }

            $this->dropUnique('worship_schedules', 'worship_schedules_branch_month_unique');

            $updates = [];
            if (Schema::hasColumn('worship_schedules', 'branch_code')) {
                $updates['branch_code'] = null;
            }
            if (Schema::hasColumn('worship_schedules', 'branch_id')) {
                $updates['branch_id'] = null;
            }
            if ($updates !== []) {
                DB::table('worship_schedules')->update($updates);
            }

            $this->addUnique('worship_schedules', ['month'], 'worship_schedules_month_unique');
        }

        if (Schema::hasTable('worship_service_schedules') && Schema::hasColumn('worship_service_schedules', 'branch_code')) {
            DB::table('worship_service_schedules')->update(['branch_code' => null]);
        }
    }

    private function removeCentralBranch(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        $branch = DB::table('branches')->where('code', 'pusat')->first();
        if ($branch === null) {
            return;
        }

        if ($this->centralBranchIsReferenced((int) $branch->id)) {
            if (Schema::hasColumn('branches', 'is_active')) {
                DB::table('branches')->where('id', $branch->id)->update(['is_active' => false]);
            }

            return;
        }

        DB::table('branches')->where('id', $branch->id)->delete();
    }

    private function centralBranchIsReferenced(int $branchId): bool
    {
        foreach ($this->branchAwareTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'branch_id') && DB::table($table)->where('branch_id', $branchId)->exists()) {
                return true;
            }
            if (Schema::hasColumn($table, 'branch_code') && DB::table($table)->where('branch_code', 'pusat')->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function branchAwareTables(): array
    {
        return [
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
        ];
    }

    private function restoreCentralBranch(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        DB::table('branches')->updateOrInsert(
            ['code' => 'pusat'],
            [
                'label' => 'Pusat',
                'sort_order' => 999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function restoreLegacyUserRoles(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'access_scope')) {
            return;
        }

        DB::table('users')->where('access_scope', 'pemuridan_cabang')->update(['access_scope' => 'branch']);
        DB::table('users')->where('access_scope', 'pemuridan_pusat')->update(['access_scope' => 'central_discipleship_readonly']);
        DB::table('users')->where('access_scope', 'pelayan')->update(['access_scope' => 'worship_only']);

        $centralBranchId = $this->branchId('pusat');
        $defaultBranchId = $this->branchId('kutisari');
        $centralUpdate = ['branch_code' => 'pusat'];
        $defaultUpdate = ['branch_code' => 'kutisari'];
        if (Schema::hasColumn('users', 'branch_id')) {
            $centralUpdate['branch_id'] = $centralBranchId;
            $defaultUpdate['branch_id'] = $defaultBranchId;
        }

        DB::table('users')->where('access_scope', 'central_discipleship_readonly')->update($centralUpdate);
        DB::table('users')->whereIn('access_scope', ['worship_only', 'developer'])->update($defaultUpdate);
    }

    private function restoreWorshipScheduleBranchScope(): void
    {
        if (! Schema::hasTable('worship_schedules')) {
            return;
        }

        $this->dropUnique('worship_schedules', 'worship_schedules_month_unique');

        $updates = [];
        if (Schema::hasColumn('worship_schedules', 'branch_code')) {
            $updates['branch_code'] = 'kutisari';
        }
        if (Schema::hasColumn('worship_schedules', 'branch_id')) {
            $updates['branch_id'] = $this->branchId('kutisari');
        }
        if ($updates !== []) {
            DB::table('worship_schedules')->update($updates);
        }

        $this->addUnique('worship_schedules', ['branch_code', 'month'], 'worship_schedules_branch_month_unique');
    }

    private function branchId(string $code): ?int
    {
        if (! Schema::hasTable('branches')) {
            return null;
        }

        $id = DB::table('branches')->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function dropUnique(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, static function (Blueprint $table) use ($indexName): void {
                $table->dropUnique($indexName);
            });
        } catch (Throwable) {
            // The index may not exist on older installations.
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addUnique(string $tableName, array $columns, string $indexName): void
    {
        try {
            Schema::table($tableName, static function (Blueprint $table) use ($columns, $indexName): void {
                $table->unique($columns, $indexName);
            });
        } catch (Throwable) {
            // The index may already exist on partially migrated installations.
        }
    }
};
