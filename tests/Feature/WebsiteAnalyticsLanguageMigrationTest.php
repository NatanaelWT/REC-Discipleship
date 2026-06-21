<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebsiteAnalyticsLanguageMigrationTest extends TestCase
{
    public function test_migration_replaces_existing_geography_columns_with_language_columns(): void
    {
        Schema::create('website_page_views', static function (Blueprint $table): void {
            $table->ulid('request_id')->primary();
            $table->string('referer_host')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('country_name', 120)->nullable();
            $table->string('region_name', 160)->nullable();
            $table->string('city_name', 160)->nullable();
            $table->dateTime('occurred_at', 6);
            $table->index(['country_code', 'occurred_at']);
        });

        $migration = require database_path('migrations/2026_06_21_000003_replace_geo_with_language_analytics.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumns('website_page_views', ['language_code', 'language_name']));
        $this->assertFalse(Schema::hasColumn('website_page_views', 'country_code'));
        $this->assertFalse(Schema::hasColumn('website_page_views', 'country_name'));
        $this->assertFalse(Schema::hasColumn('website_page_views', 'region_name'));
        $this->assertFalse(Schema::hasColumn('website_page_views', 'city_name'));
    }

    public function test_migration_is_safe_before_analytics_table_exists(): void
    {
        $migration = require database_path('migrations/2026_06_21_000003_replace_geo_with_language_analytics.php');

        $migration->up();

        $this->assertFalse(Schema::hasTable('website_page_views'));
    }

    public function test_fresh_analytics_migration_sequence_ends_with_language_schema(): void
    {
        Schema::create('activity_requests', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });
        $analyticsMigration = require database_path('migrations/2026_06_21_000002_create_website_analytics_tables.php');
        $languageMigration = require database_path('migrations/2026_06_21_000003_replace_geo_with_language_analytics.php');

        $analyticsMigration->up();
        $languageMigration->up();

        $this->assertTrue(Schema::hasColumns('website_page_views', ['language_code', 'language_name']));
        $this->assertFalse(Schema::hasColumn('website_page_views', 'country_code'));
    }
}
