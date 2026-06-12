<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('legacy_json_documents');

        $commonColumns = static function (Blueprint $table): void {
            $table->string('branch', 40)->nullable()->index();
            $table->timestamps();
        };

        Schema::create('rec_users', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('username', 120)->unique();
            $table->string('password', 255);
            $table->string('cabang', 40)->default('kutisari')->index();
            $table->string('access_scope', 80)->default('branch');
            $table->string('last_login_at_legacy', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_church_files', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('record_uid', 120)->nullable()->index();
            $table->string('title')->nullable();
            $table->string('category', 120)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('path', 500)->nullable()->index();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 180)->nullable();
            $table->string('uploaded_at_text', 80)->nullable();
            $table->string('updated_at_text', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_people_registry', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('record_uid', 120)->nullable()->index();
            $table->string('full_name')->nullable()->index();
            $table->string('whatsapp', 80)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('birth_date', 40)->nullable();
            $table->string('birth_day_month', 20)->nullable();
            $table->string('birth_place', 120)->nullable();
            $table->string('gender', 40)->nullable();
            $table->string('membership_status', 80)->nullable()->index();
            $table->string('left_at', 80)->nullable();
            $table->text('left_reason')->nullable();
            $table->longText('family_ids_json')->nullable();
            $table->longText('photos_json')->nullable();
            $table->text('social_media')->nullable();
            $table->string('msk_month', 20)->nullable()->index();
            $table->string('msk_status', 80)->nullable();
            $table->string('msk_completed_at', 80)->nullable();
            $table->string('msk_journey_bridge_status', 80)->nullable();
            $table->text('msk_notes')->nullable();
            $table->longText('msk_session_numbers_json')->nullable();
            $table->string('dg_person_id', 120)->nullable();
            $table->string('dg_member_ref', 120)->nullable();
            $table->string('dg_status', 80)->nullable();
            $table->text('dg_notes')->nullable();
            $table->string('dg_created_at', 80)->nullable();
            $table->string('dg_updated_at', 80)->nullable();
            $table->string('legacy_dg_person_id', 120)->nullable();
            $table->string('legacy_dg_role', 80)->nullable();
            $table->longText('legacy_dg_parent_ids_json')->nullable();
            $table->text('legacy_dg_notes')->nullable();
            $table->string('legacy_dg_created_at', 80)->nullable();
            $table->string('legacy_dg_updated_at', 80)->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('record_updated_at', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_discipleship_groups', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('record_uid', 120)->nullable()->index();
            $table->string('status', 80)->nullable()->index();
            $table->string('start_stage', 80)->nullable();
            $table->string('current_stage', 80)->nullable()->index();
            $table->string('parent_group_id', 120)->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('record_updated_at', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_discipleship_relationships', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('record_uid', 120)->nullable()->index();
            $table->string('relationship_kind', 80)->nullable()->index();
            $table->string('mentor_person_id', 120)->nullable()->index();
            $table->string('disciple_person_id', 120)->nullable()->index();
            $table->string('initiated_by_person_id', 120)->nullable()->index();
            $table->string('leader_person_id', 120)->nullable()->index();
            $table->string('person_id', 120)->nullable()->index();
            $table->string('group_id', 120)->nullable()->index();
            $table->string('context_group_id', 120)->nullable()->index();
            $table->string('source_group_id', 120)->nullable()->index();
            $table->string('new_group_id', 120)->nullable()->index();
            $table->string('role', 80)->nullable();
            $table->string('relation_type', 80)->nullable();
            $table->string('stage', 80)->nullable();
            $table->string('stage_at_start', 80)->nullable();
            $table->string('status', 80)->nullable()->index();
            $table->string('start_date', 40)->nullable();
            $table->string('end_date', 40)->nullable();
            $table->string('multiplication_date', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('reason_change', 120)->nullable();
            $table->string('reason_close', 120)->nullable();
            $table->string('reason_end', 120)->nullable();
            $table->string('record_created_at', 80)->nullable();
            $table->string('record_updated_at', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_dg_meeting_reports', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('record_uid', 120)->nullable()->index();
            $table->string('leader_id', 120)->nullable()->index();
            $table->string('group_id', 120)->nullable()->index();
            $table->string('meeting_date', 40)->nullable()->index();
            $table->string('material_topic')->nullable();
            $table->string('group_progress', 80)->nullable()->index();
            $table->string('absence_reason')->nullable();
            $table->longText('absent_member_ids_json')->nullable();
            $table->text('additional_notes')->nullable();
            $table->unsignedInteger('meditation_min_times')->default(0);
            $table->longText('meditation_sharer_ids_json')->nullable();
            $table->longText('meeting_photos_json')->nullable();
            $table->string('quality_pray', 80)->nullable();
            $table->string('quality_prepare', 80)->nullable();
            $table->string('quality_relational', 80)->nullable();
            $table->string('quality_share_meditation', 80)->nullable();
            $table->unsignedInteger('sharing_openness')->default(0);
            $table->string('source', 80)->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('record_updated_at', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_dg_member_feedback_journals', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('record_uid', 120)->nullable()->index();
            $table->string('branch_code', 40)->nullable()->index();
            $table->unsignedTinyInteger('feedback_session')->nullable()->index();
            $table->string('leader_id', 120)->nullable()->index();
            $table->string('leader_name')->nullable();
            $table->string('group_id', 120)->nullable()->index();
            $table->string('group_label')->nullable();
            $table->string('group_name')->nullable();
            $table->string('respondent_person_id', 120)->nullable()->index();
            $table->string('respondent_name')->nullable();
            $table->string('group_progress', 80)->nullable();
            $table->longText('notes_json')->nullable();
            $table->longText('ratings_json')->nullable();
            $table->string('source', 80)->nullable();
            $table->string('record_created_at', 80)->nullable();
            $table->string('record_updated_at', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_discipleship_targets', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->unsignedInteger('dg_total_people')->default(0);
            $table->unsignedInteger('msk_completed')->default(0);
            $table->unsignedInteger('dg1_people')->default(0);
            $table->unsignedInteger('dg2_people')->default(0);
            $table->unsignedInteger('dg3_people')->default(0);
            $commonColumns($table);
        });

        Schema::create('rec_worship_penatalayan_schedules', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('month', 20)->unique();
            $table->string('title')->nullable();
            $table->text('update_note')->nullable();
            $table->longText('rows_payload')->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('record_updated_at', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_login_attempts', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('attempt_key', 120)->unique();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('window_start_epoch')->default(0);
            $table->unsignedInteger('lock_until_epoch')->default(0);
            $table->unsignedInteger('last_epoch')->default(0);
            $commonColumns($table);
        });

        Schema::create('rec_difficult_questions', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('record_uid', 120)->nullable()->index();
            $table->string('asker_name')->nullable();
            $table->longText('question')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('password_lookup', 128)->nullable()->index();
            $table->string('status', 80)->default('pending')->index();
            $table->longText('answer')->nullable();
            $table->string('answered_by', 120)->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('answered_at_legacy', 80)->nullable();
            $table->string('record_updated_at', 80)->nullable();
            $commonColumns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rec_difficult_questions');
        Schema::dropIfExists('rec_login_attempts');
        Schema::dropIfExists('rec_worship_penatalayan_schedules');
        Schema::dropIfExists('rec_discipleship_targets');
        Schema::dropIfExists('rec_dg_member_feedback_journals');
        Schema::dropIfExists('rec_dg_meeting_reports');
        Schema::dropIfExists('rec_discipleship_relationships');
        Schema::dropIfExists('rec_discipleship_groups');
        Schema::dropIfExists('rec_people_registry');
        Schema::dropIfExists('rec_church_files');
        Schema::dropIfExists('rec_users');
    }
};
