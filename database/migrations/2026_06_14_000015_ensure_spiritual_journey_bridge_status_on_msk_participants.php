<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('msk_participants')) {
            return;
        }

        Schema::table('msk_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('msk_participants', 'journey_bridge_status')) {
                $table->string('journey_bridge_status', 80)->default('belum')->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        // This migration is only a compatibility guard for databases created
        // before the MSK tables included this column. Do not drop the column
        // on rollback because it may belong to the main MSK table migration.
    }
};
