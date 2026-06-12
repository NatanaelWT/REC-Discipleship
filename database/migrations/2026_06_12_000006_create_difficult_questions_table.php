<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('difficult_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->unique();
            $table->string('asker_name')->nullable();
            $table->longText('question');
            $table->string('password_hash')->nullable();
            $table->string('password_lookup_hash', 128)->index();
            $table->string('status', 80)->default('pending')->index();
            $table->longText('answer')->nullable();
            $table->string('answered_by_username', 120)->nullable();
            $table->timestamp('answered_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('difficult_questions');
    }
};
