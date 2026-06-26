<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discipleship_manual_journey_records')) {
            return;
        }

        Schema::create('discipleship_manual_journey_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('discipleship_people')->cascadeOnDelete();
            $table->string('stage', 80);
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'person_id', 'stage'], 'manual_journey_branch_person_stage_unique');
            $table->index(['branch_id', 'stage'], 'manual_journey_branch_stage_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipleship_manual_journey_records');
    }
};
