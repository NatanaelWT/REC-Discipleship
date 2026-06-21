<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_sessions')) {
            Schema::create('website_sessions', static function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->char('visitor_hash', 64)->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('username', 120)->nullable();
                $table->string('identity_source', 30);
                $table->dateTime('started_at', 6)->index();
                $table->dateTime('last_seen_at', 6)->index();
                $table->text('landing_path');
                $table->text('exit_path');
                $table->unsignedInteger('page_views')->default(1);

                $table->index(['visitor_hash', 'last_seen_at']);
                $table->index(['user_id', 'last_seen_at']);
            });
        }

        if (! Schema::hasTable('website_page_views')) {
            Schema::create('website_page_views', static function (Blueprint $table): void {
                $table->ulid('request_id')->primary();
                $table->ulid('session_id')->index();
                $table->char('visitor_hash', 64)->index();
                $table->string('identity_source', 30);
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('username', 120)->nullable();
                $table->string('actor_type', 20)->default('anonymous');
                $table->string('segment', 30)->index();
                $table->string('route_name', 180)->nullable();
                $table->text('path');
                $table->string('referer_host', 255)->nullable();
                $table->char('country_code', 2)->nullable();
                $table->string('country_name', 120)->nullable();
                $table->string('region_name', 160)->nullable();
                $table->string('city_name', 160)->nullable();
                $table->string('device_type', 30)->default('unknown')->index();
                $table->string('browser_name', 120)->nullable();
                $table->string('os_name', 120)->nullable();
                $table->boolean('is_bot')->default(false)->index();
                $table->boolean('is_prefetch')->default(false)->index();
                $table->unsignedSmallInteger('http_status');
                $table->decimal('response_ms', 14, 3)->nullable();
                $table->dateTime('occurred_at', 6)->index();

                $table->foreign('request_id')->references('id')->on('activity_requests')->restrictOnDelete();
                $table->foreign('session_id')->references('id')->on('website_sessions')->restrictOnDelete();
                $table->index(['is_bot', 'is_prefetch', 'occurred_at'], 'website_views_human_time_idx');
                $table->index(['visitor_hash', 'occurred_at']);
                $table->index(['session_id', 'occurred_at']);
                $table->index(['country_code', 'occurred_at']);
                $table->index(['segment', 'occurred_at']);
                $table->index(['route_name', 'occurred_at']);
                $table->index(['user_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_views');
        Schema::dropIfExists('website_sessions');
    }
};
