<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $targetColumns = [
        'camp_gap_participant_target',
        'msk_completion_target',
        'dg1_completion_target',
        'dg2_completion_target',
        'dg3_completion_target',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table): void {
            foreach ($this->targetColumns as $column) {
                if (! Schema::hasColumn('branches', $column)) {
                    $table->unsignedInteger($column)->default(50);
                }
            }
        });

        if (! Schema::hasTable('discipleship_targets')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (DB::table('discipleship_targets')->orderBy('id')->get() as $target) {
                $branchId = (int) ($target->branch_id ?? 0);
                if ($branchId < 1) {
                    continue;
                }

                $values = [];
                foreach ($this->targetColumns as $column) {
                    $values[$column] = max(0, (int) ($target->{$column} ?? 50));
                }

                DB::table('branches')->where('id', $branchId)->update($values);
            }
        });

        Schema::drop('discipleship_targets');
    }

    public function down(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        if (! Schema::hasTable('discipleship_targets')) {
            Schema::create('discipleship_targets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
                foreach ($this->targetColumns as $column) {
                    $table->unsignedInteger($column)->default(50);
                }
                $table->timestamps();
            });
        }

        if (Schema::hasColumns('branches', $this->targetColumns)) {
            $now = now();
            foreach (DB::table('branches')->orderBy('id')->get(['id', ...$this->targetColumns]) as $branch) {
                $values = [
                    'branch_id' => (int) $branch->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($this->targetColumns as $column) {
                    $values[$column] = max(0, (int) ($branch->{$column} ?? 50));
                }
                DB::table('discipleship_targets')->insert($values);
            }

            Schema::table('branches', function (Blueprint $table): void {
                $table->dropColumn($this->targetColumns);
            });
        }
    }
};
