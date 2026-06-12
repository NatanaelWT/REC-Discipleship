<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE worship_service_schedules MODIFY update_note LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE worship_service_schedules MODIFY update_note VARCHAR(255) NULL');
    }
};
