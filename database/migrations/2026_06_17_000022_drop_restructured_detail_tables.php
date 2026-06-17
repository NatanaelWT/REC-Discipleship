<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'msk_participant_photos',
            'msk_participant_sessions',
            'discipleship_meeting_report_photos',
            'discipleship_meeting_report_meditation_sharers',
            'discipleship_meeting_report_absences',
            'discipleship_group_multiplications',
            'discipleship_group_leaderships',
            'discipleship_group_memberships',
            'discipleship_member_feedback_notes',
            'discipleship_member_feedback_ratings',
            'discipleship_member_feedback_journals',
            'public_material_menu_files',
            'church_files',
            'worship_service_assignments',
            'worship_service_schedule_weeks',
            'worship_service_schedule_roles',
            'worship_service_schedules',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Intentionally not reversible. Data is preserved in the v2 tables/JSON columns.
    }
};
