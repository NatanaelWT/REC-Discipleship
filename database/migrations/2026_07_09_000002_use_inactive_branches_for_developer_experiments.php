<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cabang')) {
            return;
        }

        $now = now();
        $hasUpdatedAt = Schema::hasColumn('cabang', 'updated_at');

        if (Schema::hasColumn('cabang', 'is_developer_only')) {
            $developerOnlyUpdate = ['is_active' => false];
            if ($hasUpdatedAt) {
                $developerOnlyUpdate['updated_at'] = $now;
            }

            DB::table('cabang')
                ->where('is_developer_only', true)
                ->update($developerOnlyUpdate);

            Schema::table('cabang', static function (Blueprint $table): void {
                $table->dropColumn('is_developer_only');
            });
        }

        $values = [
            'label' => 'Testing',
            'is_active' => false,
        ];
        if ($hasUpdatedAt) {
            $values['updated_at'] = $now;
        }

        foreach ($this->targetColumns() as $column) {
            if (Schema::hasColumn('cabang', $column)) {
                $values[$column] = 50;
            }
        }

        $exists = DB::table('cabang')->where('label', 'Testing')->exists();
        if (! $exists && Schema::hasColumn('cabang', 'created_at')) {
            $values['created_at'] = $now;
        }

        DB::table('cabang')->updateOrInsert(['label' => 'Testing'], $values);

        $this->clearBranchCatalogCache();
    }

    public function down(): void
    {
        $this->clearBranchCatalogCache();
    }

    /** @return array<int, string> */
    private function targetColumns(): array
    {
        return [
            'camp_gap_participant_target',
            'msk_completion_target',
            'dg1_completion_target',
            'dg2_completion_target',
            'dg3_completion_target',
        ];
    }

    private function clearBranchCatalogCache(): void
    {
        $store = app()->environment('testing') ? 'array' : 'file';
        Cache::store($store)->forget('rec.branch-catalog.v3');
        Cache::store($store)->forget('rec.branch-catalog.v4');
    }
};
