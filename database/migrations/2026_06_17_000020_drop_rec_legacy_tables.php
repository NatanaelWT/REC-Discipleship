<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->obsoleteRecTables() as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Obsolete rec_* tables are intentionally not recreated.
    }

    /**
     * @return array<int, string>
     */
    private function obsoleteRecTables(): array
    {
        return [
            'rec_difficult_questions',
            'rec_login_attempts',
            'rec_worship_penatalayan_schedules',
            'rec_discipleship_targets',
            'rec_dg_member_feedback_journals',
            'rec_dg_meeting_reports',
            'rec_discipleship_relationships',
            'rec_discipleship_groups',
            'rec_people_registry',
            'rec_church_files',
            'rec_users',
        ];
    }
};
