<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_requests')) {
            Schema::create('activity_requests', static function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('actor_type', 20)->default('anonymous')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('username', 120)->nullable();
                $table->string('role', 80)->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('branch_label', 160)->nullable();
                $table->char('visitor_hash', 64)->nullable()->index();
                $table->string('ip_address', 45)->nullable()->index();
                $table->string('method', 12)->index();
                $table->string('route_name', 180)->nullable();
                $table->text('path');
                $table->string('category', 60)->default('request');
                $table->string('action', 180)->default('request')->index();
                $table->string('subject_type', 160)->nullable();
                $table->string('subject_id', 191)->nullable()->index();
                $table->json('query_data')->nullable();
                $table->json('input_data')->nullable();
                $table->unsignedSmallInteger('http_status')->nullable()->index();
                $table->string('outcome', 30)->default('pending');
                $table->text('redirect_to')->nullable();
                $table->string('response_content_type', 180)->nullable();
                $table->unsignedBigInteger('response_size')->nullable();
                $table->decimal('duration_ms', 14, 3)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('referer')->nullable();
                $table->string('error_type', 191)->nullable();
                $table->text('error_message')->nullable();
                $table->dateTime('started_at', 6)->index();
                $table->dateTime('completed_at', 6)->nullable();

                $table->index(['username', 'started_at']);
                $table->index(['category', 'started_at']);
                $table->index(['outcome', 'started_at']);
                $table->index(['route_name', 'started_at']);
                $table->index(['subject_type', 'subject_id']);
            });
        }

        if (! Schema::hasTable('activity_events')) {
            Schema::create('activity_events', static function (Blueprint $table): void {
                $table->id();
                $table->ulid('request_id');
                $table->string('category', 60);
                $table->string('action', 180)->index();
                $table->string('subject_type', 160)->nullable();
                $table->string('subject_id', 191)->nullable()->index();
                $table->string('subject_label', 255)->nullable();
                $table->text('description')->nullable();
                $table->json('before_values')->nullable();
                $table->json('after_values')->nullable();
                $table->json('changed_values')->nullable();
                $table->json('metadata')->nullable();
                $table->dateTime('occurred_at', 6);

                $table->foreign('request_id')
                    ->references('id')
                    ->on('activity_requests')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->index(['request_id', 'id']);
                $table->index(['category', 'occurred_at']);
                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('activity_requests');
    }
};
