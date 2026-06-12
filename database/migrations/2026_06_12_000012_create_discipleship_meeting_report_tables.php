<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120);
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->string('leader_person_public_id', 120)->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('discipleship_group_public_id', 120)->nullable();
            $table->string('group_name_snapshot')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('material_topic')->nullable();
            $table->string('group_progress_snapshot', 80)->nullable();
            $table->text('absence_reason')->nullable();
            $table->longText('additional_notes')->nullable();
            $table->unsignedTinyInteger('meditation_min_times')->default(0);
            $table->unsignedTinyInteger('sharing_openness_score')->nullable();
            $table->boolean('prepared_material')->default(false);
            $table->boolean('prayed_for_members')->default(false);
            $table->boolean('shared_meditation')->default(false);
            $table->boolean('relationally_contacted')->default(false);
            $table->string('source', 80)->default('public_form');
            $table->timestamps();

            $table->unique('public_id', 'dm_reports_public_id_unique');
            $table->index('branch_code', 'dm_reports_branch_index');
            $table->index('leader_person_id', 'dm_reports_leader_index');
            $table->index('leader_person_public_id', 'dm_reports_leader_public_index');
            $table->index('discipleship_group_id', 'dm_reports_group_index');
            $table->index('discipleship_group_public_id', 'dm_reports_group_public_index');
            $table->index('meeting_date', 'dm_reports_meeting_date_index');
            $table->index(['branch_code', 'meeting_date'], 'dm_reports_branch_date_index');
        });

        Schema::create('discipleship_meeting_report_absences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_meeting_report_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id', 120)->nullable();
            $table->string('person_name_snapshot')->nullable();
            $table->timestamps();

            $table->index('discipleship_meeting_report_id', 'dm_absences_report_index');
            $table->index('person_id', 'dm_absences_person_index');
            $table->index('person_public_id', 'dm_absences_person_public_index');
            $table->unique(
                ['discipleship_meeting_report_id', 'person_public_id'],
                'dm_absences_report_person_public_unique'
            );
            $table->foreign(
                'discipleship_meeting_report_id',
                'dm_absences_report_fk'
            )->references('id')->on('discipleship_meeting_reports')->cascadeOnDelete();
        });

        Schema::create('discipleship_meeting_report_meditation_sharers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_meeting_report_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id', 120)->nullable();
            $table->string('person_name_snapshot')->nullable();
            $table->timestamps();

            $table->index('discipleship_meeting_report_id', 'dm_sharers_report_index');
            $table->index('person_id', 'dm_sharers_person_index');
            $table->index('person_public_id', 'dm_sharers_person_public_index');
            $table->unique(
                ['discipleship_meeting_report_id', 'person_public_id'],
                'dm_sharers_report_person_public_unique'
            );
            $table->foreign(
                'discipleship_meeting_report_id',
                'dm_sharers_report_fk'
            )->references('id')->on('discipleship_meeting_reports')->cascadeOnDelete();
        });

        Schema::create('discipleship_meeting_report_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_meeting_report_id');
            $table->string('relative_path', 500);
            $table->string('original_file_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('discipleship_meeting_report_id', 'dm_photos_report_index');
            $table->index('relative_path', 'dm_photos_path_index');
            $table->index('sort_order', 'dm_photos_sort_index');
            $table->foreign(
                'discipleship_meeting_report_id',
                'dm_photos_report_fk'
            )->references('id')->on('discipleship_meeting_reports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_meeting_report_photos');
        Schema::dropIfExists('discipleship_meeting_report_meditation_sharers');
        Schema::dropIfExists('discipleship_meeting_report_absences');
        Schema::dropIfExists('discipleship_meeting_reports');
    }
};
