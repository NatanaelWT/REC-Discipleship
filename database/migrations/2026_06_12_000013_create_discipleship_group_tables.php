<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120);
            $table->string('branch_code', 40);
            $table->string('member_public_id', 120)->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('gender', 40)->nullable();
            $table->string('status', 80)->default('active');
            $table->longText('notes')->nullable();
            $table->string('campus')->nullable();
            $table->string('major')->nullable();
            $table->string('occupation')->nullable();
            $table->timestamps();

            $table->unique(['branch_code', 'public_id'], 'discipleship_people_branch_public_unique');
            $table->index('public_id', 'discipleship_people_public_index');
            $table->index('branch_code', 'discipleship_people_branch_index');
            $table->index('member_public_id', 'discipleship_people_member_public_index');
            $table->index('status', 'discipleship_people_status_index');
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120);
            $table->string('branch_code', 40);
            $table->string('name')->default('Kelompok');
            $table->string('status', 80)->default('active');
            $table->string('start_stage', 80)->nullable();
            $table->string('current_stage', 80)->nullable();
            $table->foreignId('parent_group_id')->nullable()->constrained('discipleship_groups')->nullOnDelete();
            $table->string('parent_group_public_id', 120)->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_code', 'public_id'], 'discipleship_groups_branch_public_unique');
            $table->index('public_id', 'discipleship_groups_public_index');
            $table->index('branch_code', 'discipleship_groups_branch_index');
            $table->index('status', 'discipleship_groups_status_index');
            $table->index('current_stage', 'discipleship_groups_current_stage_index');
            $table->index('parent_group_public_id', 'discipleship_groups_parent_public_index');
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->nullable();
            $table->string('branch_code', 40);
            $table->foreignId('mentor_person_id')->nullable()->constrained('discipleship_people')->nullOnDelete();
            $table->string('mentor_person_public_id', 120)->nullable();
            $table->foreignId('disciple_person_id')->nullable()->constrained('discipleship_people')->nullOnDelete();
            $table->string('disciple_person_public_id', 120)->nullable();
            $table->foreignId('context_group_id')->nullable()->constrained('discipleship_groups')->nullOnDelete();
            $table->string('context_group_public_id', 120)->nullable();
            $table->string('relation_type', 120)->nullable();
            $table->string('stage_at_start', 80)->nullable();
            $table->string('status', 80)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_code', 'public_id'], 'discipleship_relations_branch_public_unique');
            $table->index('branch_code', 'discipleship_relations_branch_index');
            $table->index('mentor_person_public_id', 'discipleship_relations_mentor_public_index');
            $table->index('disciple_person_public_id', 'discipleship_relations_disciple_public_index');
            $table->index('context_group_public_id', 'discipleship_relations_context_public_index');
            $table->index('status', 'discipleship_relations_status_index');
        });

        Schema::create('discipleship_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->nullable();
            $table->string('branch_code', 40);
            $table->foreignId('discipleship_group_id')->constrained('discipleship_groups')->cascadeOnDelete();
            $table->string('group_public_id', 120);
            $table->foreignId('person_id')->nullable()->constrained('discipleship_people')->nullOnDelete();
            $table->string('person_public_id', 120)->nullable();
            $table->string('role', 80)->default('member');
            $table->string('stage', 80)->nullable();
            $table->string('status', 80)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->timestamps();

            $table->unique(['branch_code', 'public_id'], 'dg_memberships_branch_public_unique');
            $table->index('branch_code', 'dg_memberships_branch_index');
            $table->index('group_public_id', 'dg_memberships_group_public_index');
            $table->index('person_public_id', 'dg_memberships_person_public_index');
            $table->index('status', 'dg_memberships_status_index');
            $table->index('stage', 'dg_memberships_stage_index');
        });

        Schema::create('discipleship_group_leaderships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->nullable();
            $table->string('branch_code', 40);
            $table->foreignId('discipleship_group_id')->constrained('discipleship_groups')->cascadeOnDelete();
            $table->string('group_public_id', 120);
            $table->foreignId('person_id')->nullable()->constrained('discipleship_people')->nullOnDelete();
            $table->string('person_public_id', 120)->nullable();
            $table->string('role', 80)->default('leader');
            $table->string('status', 80)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_change')->nullable();
            $table->timestamps();

            $table->unique(['branch_code', 'public_id'], 'dg_leaderships_branch_public_unique');
            $table->index('branch_code', 'dg_leaderships_branch_index');
            $table->index('group_public_id', 'dg_leaderships_group_public_index');
            $table->index('person_public_id', 'dg_leaderships_person_public_index');
            $table->index('role', 'dg_leaderships_role_index');
            $table->index('status', 'dg_leaderships_status_index');
        });

        Schema::create('discipleship_group_multiplications', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->nullable();
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->string('initiated_by_person_public_id', 120)->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->string('source_group_public_id', 120)->nullable();
            $table->unsignedBigInteger('new_group_id')->nullable();
            $table->string('new_group_public_id', 120)->nullable();
            $table->date('multiplication_date')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_code', 'public_id'], 'dg_multiplications_branch_public_unique');
            $table->index('branch_code', 'dg_multiplications_branch_index');
            $table->index('initiated_by_person_public_id', 'dg_multiplications_initiator_public_index');
            $table->index('source_group_public_id', 'dg_multiplications_source_public_index');
            $table->index('new_group_public_id', 'dg_multiplications_new_public_index');
            $table->foreign('initiated_by_person_id', 'dg_multiplications_initiator_fk')
                ->references('id')
                ->on('discipleship_people')
                ->nullOnDelete();
            $table->foreign('source_group_id', 'dg_multiplications_source_group_fk')
                ->references('id')
                ->on('discipleship_groups')
                ->nullOnDelete();
            $table->foreign('new_group_id', 'dg_multiplications_new_group_fk')
                ->references('id')
                ->on('discipleship_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_group_multiplications');
        Schema::dropIfExists('discipleship_group_leaderships');
        Schema::dropIfExists('discipleship_group_memberships');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');
    }
};
