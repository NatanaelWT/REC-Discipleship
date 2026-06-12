<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipleship_member_feedback_journals', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120);
            $table->string('branch_code', 40);
            $table->unsignedTinyInteger('feedback_session');
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->unsignedBigInteger('respondent_person_id')->nullable();
            $table->string('respondent_name_snapshot')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->string('group_name_snapshot')->nullable();
            $table->string('group_label_snapshot')->nullable();
            $table->string('group_progress_snapshot', 80)->nullable();
            $table->string('source', 80)->default('public_form');
            $table->timestamps();

            $table->unique('public_id', 'dmf_journals_public_id_unique');
            $table->index('branch_code', 'dmf_journals_branch_index');
            $table->index('feedback_session', 'dmf_journals_session_index');
            $table->index('discipleship_group_id', 'dmf_journals_group_index');
            $table->index('leader_person_id', 'dmf_journals_leader_index');
            $table->index('respondent_person_id', 'dmf_journals_respondent_index');
            $table->index(['branch_code', 'feedback_session'], 'dmf_journals_branch_session_index');
        });

        Schema::create('discipleship_member_feedback_ratings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_member_feedback_journal_id');
            $table->string('section_key', 80)->nullable();
            $table->string('question_key', 120);
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('scale');
            $table->timestamps();

            $table->index('discipleship_member_feedback_journal_id', 'dmf_ratings_journal_index');
            $table->index('section_key', 'dmf_ratings_section_index');
            $table->unique(
                ['discipleship_member_feedback_journal_id', 'question_key'],
                'dmf_ratings_journal_question_unique'
            );
            $table->foreign(
                'discipleship_member_feedback_journal_id',
                'dmf_ratings_journal_fk'
            )->references('id')->on('discipleship_member_feedback_journals')->cascadeOnDelete();
        });

        Schema::create('discipleship_member_feedback_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('discipleship_member_feedback_journal_id');
            $table->string('section_key', 80)->nullable();
            $table->string('note_key', 120);
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->index('discipleship_member_feedback_journal_id', 'dmf_notes_journal_index');
            $table->index('section_key', 'dmf_notes_section_index');
            $table->unique(
                ['discipleship_member_feedback_journal_id', 'note_key'],
                'dmf_notes_journal_note_unique'
            );
            $table->foreign(
                'discipleship_member_feedback_journal_id',
                'dmf_notes_journal_fk'
            )->references('id')->on('discipleship_member_feedback_journals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_member_feedback_notes');
        Schema::dropIfExists('discipleship_member_feedback_ratings');
        Schema::dropIfExists('discipleship_member_feedback_journals');
    }
};
