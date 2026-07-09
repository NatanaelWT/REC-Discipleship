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

        if (! Schema::hasColumn('cabang', 'is_developer_only')) {
            Schema::table('cabang', static function (Blueprint $table): void {
                $table->boolean('is_developer_only')->default(false)->after('is_active');
            });
        }

        $now = now();
        $values = [
            'label' => 'Testing',
            'is_active' => true,
            'is_developer_only' => true,
        ];
        if (Schema::hasColumn('cabang', 'updated_at')) {
            $values['updated_at'] = $now;
        }

        foreach ([
            'camp_gap_participant_target',
            'msk_completion_target',
            'dg1_completion_target',
            'dg2_completion_target',
            'dg3_completion_target',
        ] as $column) {
            if (Schema::hasColumn('cabang', $column)) {
                $values[$column] = 50;
            }
        }

        $exists = DB::table('cabang')->where('label', 'Testing')->exists();
        DB::table('cabang')->updateOrInsert(
            ['label' => 'Testing'],
            $exists || ! Schema::hasColumn('cabang', 'created_at') ? $values : array_merge($values, ['created_at' => $now]),
        );

        Cache::store(app()->environment('testing') ? 'array' : 'file')->forget('rec.branch-catalog.v3');
    }

    public function down(): void
    {
        if (! Schema::hasTable('cabang') || ! Schema::hasColumn('cabang', 'is_developer_only')) {
            return;
        }

        Schema::table('cabang', static function (Blueprint $table): void {
            $table->dropColumn('is_developer_only');
        });

        Cache::store(app()->environment('testing') ? 'array' : 'file')->forget('rec.branch-catalog.v3');
    }
};
