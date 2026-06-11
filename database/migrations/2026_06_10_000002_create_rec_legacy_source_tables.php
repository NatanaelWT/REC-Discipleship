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
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('legacy_id', 120)->nullable()->index();
            $table->string('branch', 40)->nullable()->index();
            $table->longText('payload');
            $table->string('payload_checksum', 64)->default('');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();
        };

        $documentColumns = static function (Blueprint $table): void {
            $table->unsignedInteger('document_schema_version')->nullable();
            $table->string('document_name', 120)->nullable();
            $table->string('document_updated_at', 80)->nullable();
            $table->longText('document_branches')->nullable();
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
            $table->string('title')->nullable();
            $table->string('category', 120)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('path', 500)->nullable()->index();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 180)->nullable();
            $table->string('uploaded_at_legacy', 80)->nullable();
            $table->string('updated_at_legacy', 80)->nullable();
            $commonColumns($table);
        });

        Schema::create('rec_people_registry', function (Blueprint $table) use ($commonColumns, $documentColumns): void {
            $table->id();
            $table->string('full_name')->nullable()->index();
            $table->string('whatsapp', 80)->nullable();
            $table->string('email')->nullable();
            $table->string('membership_status', 80)->nullable()->index();
            $table->string('msk_month', 20)->nullable()->index();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('updated_at_legacy', 80)->nullable();
            $documentColumns($table);
            $commonColumns($table);
        });

        Schema::create('rec_discipleship_groups', function (Blueprint $table) use ($commonColumns, $documentColumns): void {
            $table->id();
            $table->string('status', 80)->nullable()->index();
            $table->string('start_stage', 80)->nullable();
            $table->string('current_stage', 80)->nullable()->index();
            $table->string('parent_group_id', 120)->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('updated_at_legacy', 80)->nullable();
            $documentColumns($table);
            $commonColumns($table);
        });

        Schema::create('rec_discipleship_relationships', function (Blueprint $table) use ($commonColumns, $documentColumns): void {
            $table->id();
            $table->string('relationship_kind', 80)->nullable()->index();
            $table->string('mentor_person_id', 120)->nullable()->index();
            $table->string('disciple_person_id', 120)->nullable()->index();
            $table->string('person_id', 120)->nullable()->index();
            $table->string('group_id', 120)->nullable()->index();
            $table->string('context_group_id', 120)->nullable()->index();
            $table->string('role', 80)->nullable();
            $table->string('stage', 80)->nullable();
            $table->string('status', 80)->nullable()->index();
            $table->string('start_date', 40)->nullable();
            $table->string('end_date', 40)->nullable();
            $documentColumns($table);
            $commonColumns($table);
        });

        Schema::create('rec_dg_meeting_reports', function (Blueprint $table) use ($commonColumns, $documentColumns): void {
            $table->id();
            $table->string('leader_id', 120)->nullable()->index();
            $table->string('group_id', 120)->nullable()->index();
            $table->string('meeting_date', 40)->nullable()->index();
            $table->string('material_topic')->nullable();
            $table->string('group_progress', 80)->nullable()->index();
            $table->string('source', 80)->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('updated_at_legacy', 80)->nullable();
            $documentColumns($table);
            $commonColumns($table);
        });

        Schema::create('rec_dg_member_feedback_journals', function (Blueprint $table) use ($commonColumns, $documentColumns): void {
            $table->id();
            $table->string('branch_code', 40)->nullable()->index();
            $table->unsignedTinyInteger('feedback_session')->nullable()->index();
            $table->string('leader_id', 120)->nullable()->index();
            $table->string('group_id', 120)->nullable()->index();
            $table->string('respondent_person_id', 120)->nullable()->index();
            $table->string('respondent_name')->nullable();
            $table->string('group_progress', 80)->nullable();
            $table->string('source', 80)->nullable();
            $documentColumns($table);
            $commonColumns($table);
        });

        Schema::create('rec_discipleship_targets', function (Blueprint $table) use ($commonColumns, $documentColumns): void {
            $table->id();
            $table->unsignedInteger('dg_total_people')->default(0);
            $table->unsignedInteger('msk_completed')->default(0);
            $table->unsignedInteger('dg1_people')->default(0);
            $table->unsignedInteger('dg2_people')->default(0);
            $table->unsignedInteger('dg3_people')->default(0);
            $documentColumns($table);
            $commonColumns($table);
        });

        Schema::create('rec_worship_penatalayan_schedules', function (Blueprint $table) use ($commonColumns): void {
            $table->id();
            $table->string('month', 20)->unique();
            $table->string('title')->nullable();
            $table->text('update_note')->nullable();
            $table->longText('rows_payload')->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('updated_at_legacy', 80)->nullable();
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
            $table->string('asker_name')->nullable();
            $table->string('password_lookup', 128)->nullable()->index();
            $table->string('status', 80)->default('pending')->index();
            $table->string('answered_by', 120)->nullable();
            $table->string('created_at_legacy', 80)->nullable();
            $table->string('answered_at_legacy', 80)->nullable();
            $table->string('updated_at_legacy', 80)->nullable();
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
