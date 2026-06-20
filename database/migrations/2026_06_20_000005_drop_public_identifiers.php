<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $columns = [
        'difficult_questions' => ['public_id'],
        'discipleship_feedbacks' => ['public_id'],
        'discipleship_people' => ['public_id', 'member_public_id'],
        'discipleship_groups' => ['public_id', 'parent_group_public_id', 'source_group_public_id', 'initiated_by_person_public_id'],
        'discipleship_relationships' => ['public_id', 'mentor_person_public_id', 'disciple_person_public_id', 'context_group_public_id'],
        'discipleship_group_people' => ['public_id', 'group_public_id', 'person_public_id'],
        'discipleship_meeting_reports' => ['public_id', 'leader_person_public_id', 'discipleship_group_public_id'],
        'msk_participants' => ['public_id', 'member_public_id'],
        'public_material_files' => ['public_id'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $candidateColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                $candidateColumns,
                static fn (string $column): bool => Schema::hasColumn($table, $column),
            ));
            if ($columns === []) {
                continue;
            }

            $this->ensureBranchIndexSurvives($table, $columns);

            foreach (Schema::getIndexes($table) as $index) {
                if ($index['primary'] || array_intersect($columns, $index['columns']) === []) {
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

            Schema::table($table, static function (Blueprint $blueprint) use ($columns): void {
                $blueprint->dropColumn($columns);
            });
        }
    }

    /** @param array<int, string> $legacyColumns */
    private function ensureBranchIndexSurvives(string $table, array $legacyColumns): void
    {
        if (! Schema::hasColumn($table, 'branch_id')) {
            return;
        }

        $hasSurvivingBranchIndex = false;
        foreach (Schema::getIndexes($table) as $index) {
            $indexColumns = $index['columns'] ?? [];
            if (($indexColumns[0] ?? null) === 'branch_id'
                && array_intersect($legacyColumns, $indexColumns) === []) {
                $hasSurvivingBranchIndex = true;
                break;
            }
        }

        if ($hasSurvivingBranchIndex) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($table): void {
            $blueprint->index('branch_id', $table.'_branch_id_index');
        });
    }

    public function down(): void
    {
        // This migration intentionally removes identifiers that cannot be reconstructed safely.
    }
};
