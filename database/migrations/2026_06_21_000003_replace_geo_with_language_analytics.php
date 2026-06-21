<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_page_views')) {
            return;
        }

        if (Schema::hasColumn('website_page_views', 'country_code')) {
            Schema::table('website_page_views', static function (Blueprint $table): void {
                $table->dropIndex('website_page_views_country_code_occurred_at_index');
                $table->dropColumn(['country_code', 'country_name', 'region_name', 'city_name']);
            });
        }

        if (! Schema::hasColumn('website_page_views', 'language_code')) {
            Schema::table('website_page_views', static function (Blueprint $table): void {
                $table->string('language_code', 20)->nullable()->after('referer_host');
                $table->string('language_name', 100)->nullable()->after('language_code');
                $table->index(['language_code', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_page_views')) {
            return;
        }

        if (Schema::hasColumn('website_page_views', 'language_code')) {
            Schema::table('website_page_views', static function (Blueprint $table): void {
                $table->dropIndex('website_page_views_language_code_occurred_at_index');
                $table->dropColumn(['language_code', 'language_name']);
            });
        }

        if (! Schema::hasColumn('website_page_views', 'country_code')) {
            Schema::table('website_page_views', static function (Blueprint $table): void {
                $table->char('country_code', 2)->nullable();
                $table->string('country_name', 120)->nullable();
                $table->string('region_name', 160)->nullable();
                $table->string('city_name', 160)->nullable();
                $table->index(['country_code', 'occurred_at']);
            });
        }
    }
};
