<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_requests')) {
            return;
        }

        Schema::table('activity_requests', static function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_requests', 'impersonator_user_id')) {
                $table->unsignedBigInteger('impersonator_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('activity_requests', 'impersonator_username')) {
                $table->string('impersonator_username', 120)->nullable();
            }
            if (! Schema::hasColumn('activity_requests', 'impersonator_role')) {
                $table->string('impersonator_role', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_requests')) {
            return;
        }

        Schema::table('activity_requests', static function (Blueprint $table): void {
            foreach (['impersonator_user_id', 'impersonator_username', 'impersonator_role'] as $column) {
                if (Schema::hasColumn('activity_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
