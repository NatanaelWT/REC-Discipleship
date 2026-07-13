<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_activities', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('actor_type', 20)->default('anonymous');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username', 120)->nullable();
            $table->string('role', 80)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_label', 160)->nullable();
            $table->unsignedBigInteger('impersonator_user_id')->nullable();
            $table->string('impersonator_username', 120)->nullable();
            $table->string('impersonator_role', 80)->nullable();
            $table->char('visitor_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('method', 12);
            $table->string('route_name', 180)->nullable();
            $table->text('path');
            $table->string('category', 60)->default('request');
            $table->string('action', 180)->default('request');
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 191)->nullable();
            $table->json('query_data')->nullable();
            $table->json('input_data')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome', 30)->default('pending');
            $table->text('redirect_to')->nullable();
            $table->string('response_content_type', 180)->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->decimal('duration_ms', 14, 3)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->string('error_type', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_page_view')->default(false);
            $table->string('identity_source', 30)->nullable();
            $table->string('segment', 30)->nullable();
            $table->string('referer_host', 255)->nullable();
            $table->string('language_code', 20)->nullable();
            $table->string('language_name', 100)->nullable();
            $table->string('device_type', 30)->nullable();
            $table->string('browser_name', 120)->nullable();
            $table->string('os_name', 120)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_prefetch')->default(false);
            $table->decimal('response_ms', 14, 3)->nullable();
            $table->dateTime('occurred_at', 6)->nullable();
            $table->unsignedInteger('events_count')->default(0);
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();

            // Only indexes backed by current request, drill-down, and retention queries.
            $table->index('started_at');
            $table->index(['user_id', 'started_at']);
            $table->index(['visitor_hash', 'started_at']);
            $table->index(['route_name', 'started_at']);
            $table->index(['is_page_view', 'occurred_at']);
        });

        Schema::create('audit_events', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('request_id');
            $table->string('category', 60);
            $table->string('action', 180);
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 191)->nullable();
            $table->string('subject_label', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('changed_values')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->foreign('request_id')
                ->references('id')
                ->on('request_activities')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index(['request_id', 'occurred_at']);
            $table->index('occurred_at');
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('website_daily_rollups', static function (Blueprint $table): void {
            $table->id();
            $table->date('activity_date');
            $table->char('dimension_hash', 64)->unique();
            $table->string('rollup_scope', 20)->default('detail');
            $table->string('segment', 30)->default('');
            $table->string('route_name', 180)->default('');
            $table->string('path', 512)->default('');
            $table->string('language_code', 20)->default('');
            $table->string('device_type', 30)->default('');
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('human_page_views')->default(0);
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->unsignedBigInteger('human_unique_visitors')->default(0);
            $table->unsignedBigInteger('bot_views')->default(0);
            $table->unsignedBigInteger('prefetch_views')->default(0);
            $table->decimal('total_response_ms', 18, 3)->default(0);
            $table->decimal('average_response_ms', 14, 3)->default(0);
            $table->decimal('human_total_response_ms', 18, 3)->default(0);
            $table->decimal('human_average_response_ms', 14, 3)->default(0);
            $table->timestamps();

            $table->index('activity_date');
            $table->index(['activity_date', 'rollup_scope']);
            $table->index(['segment', 'activity_date']);
            $table->index(['route_name', 'activity_date']);
        });

        Schema::create('maintenance_runs', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('idempotency_key', 64)->unique();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->string('requested_by_username', 120);
            $table->string('status', 20)->default('pending');
            $table->boolean('dry_run')->default(false);
            $table->json('cursor')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'heartbeat_at']);
            $table->index(['requested_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_runs');
        Schema::dropIfExists('website_daily_rollups');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('request_activities');
    }
};
