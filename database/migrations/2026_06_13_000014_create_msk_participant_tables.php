<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120);
            $table->string('branch_code', 40);
            $table->string('member_public_id', 120)->nullable();
            $table->string('full_name')->nullable();
            $table->string('gender', 40)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_day_month', 20)->nullable();
            $table->string('birth_place', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp', 80)->nullable();
            $table->string('batch_month', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('completed_at', 80)->nullable();
            $table->string('journey_bridge_status', 80)->default('belum');
            $table->string('status', 80)->default('active');
            $table->timestamps();

            $table->unique(['branch_code', 'public_id'], 'msk_participants_branch_public_unique');
            $table->index('public_id', 'msk_participants_public_index');
            $table->index('branch_code', 'msk_participants_branch_index');
            $table->index('member_public_id', 'msk_participants_member_public_index');
            $table->index('batch_month', 'msk_participants_batch_month_index');
            $table->index('status', 'msk_participants_status_index');
        });

        Schema::create('msk_participant_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('msk_participant_id')->constrained('msk_participants')->cascadeOnDelete();
            $table->unsignedTinyInteger('session_number');
            $table->timestamps();

            $table->unique(['msk_participant_id', 'session_number'], 'msk_sessions_participant_number_unique');
            $table->index('session_number', 'msk_sessions_number_index');
        });

        Schema::create('msk_participant_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('msk_participant_id')->constrained('msk_participants')->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->unique(['msk_participant_id', 'path'], 'msk_photos_participant_path_unique');
            $table->index('path', 'msk_photos_path_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msk_participant_photos');
        Schema::dropIfExists('msk_participant_sessions');
        Schema::dropIfExists('msk_participants');
    }
};
