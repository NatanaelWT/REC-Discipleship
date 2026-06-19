<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', static function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('access_scope');
            });
        }

        if (! Schema::hasTable('app_configs')) {
            Schema::create('app_configs', static function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->unique();
                $table->text('value')->nullable();
                $table->string('updated_by', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_configs');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', static function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
