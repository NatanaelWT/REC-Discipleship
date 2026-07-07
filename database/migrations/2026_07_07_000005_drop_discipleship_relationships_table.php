<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('relasi_dg');
            Schema::dropIfExists('discipleship_relationships');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('relasi_dg')) {
            return;
        }

        Schema::create('relasi_dg', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('mentor_person_id')->nullable()->index();
            $table->unsignedBigInteger('disciple_person_id')->nullable()->index();
            $table->unsignedBigInteger('context_group_id')->nullable()->index();
            $table->string('relation_type', 120)->nullable();
            $table->string('stage_at_start', 80)->nullable();
            $table->string('status', 80)->default('active')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
        });
    }
};
