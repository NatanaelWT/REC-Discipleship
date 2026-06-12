<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worship_service_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->unique();
            $table->string('title');
            $table->string('update_note')->nullable();
            $table->string('branch_code', 40)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('worship_service_schedule_roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('worship_service_schedule_id');
            $table->string('role_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('worship_service_schedule_id', 'wss_roles_schedule_index');
            $table->index('sort_order', 'wss_roles_sort_order_index');
            $table->foreign('worship_service_schedule_id', 'wss_roles_schedule_fk')
                ->references('id')->on('worship_service_schedules')->cascadeOnDelete();
        });

        Schema::create('worship_service_schedule_weeks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('worship_service_schedule_id');
            $table->unsignedTinyInteger('week_index');
            $table->date('service_date');
            $table->date('training_date')->nullable();
            $table->timestamps();

            $table->unique(
                ['worship_service_schedule_id', 'week_index'],
                'wss_weeks_schedule_week_unique'
            );
            $table->unique(
                ['worship_service_schedule_id', 'service_date'],
                'wss_weeks_schedule_date_unique'
            );
            $table->foreign('worship_service_schedule_id', 'wss_weeks_schedule_fk')
                ->references('id')->on('worship_service_schedules')->cascadeOnDelete();
        });

        Schema::create('worship_service_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('worship_service_schedule_role_id');
            $table->unsignedBigInteger('worship_service_schedule_week_id');
            $table->string('assignee_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('worship_service_schedule_role_id', 'wss_assignments_role_index');
            $table->index('worship_service_schedule_week_id', 'wss_assignments_week_index');
            $table->unique(
                ['worship_service_schedule_role_id', 'worship_service_schedule_week_id', 'sort_order'],
                'wss_assignments_role_week_sort_unique'
            );
            $table->foreign('worship_service_schedule_role_id', 'wss_assignments_role_fk')
                ->references('id')->on('worship_service_schedule_roles')->cascadeOnDelete();
            $table->foreign('worship_service_schedule_week_id', 'wss_assignments_week_fk')
                ->references('id')->on('worship_service_schedule_weeks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worship_service_assignments');
        Schema::dropIfExists('worship_service_schedule_weeks');
        Schema::dropIfExists('worship_service_schedule_roles');
        Schema::dropIfExists('worship_service_schedules');
    }
};
