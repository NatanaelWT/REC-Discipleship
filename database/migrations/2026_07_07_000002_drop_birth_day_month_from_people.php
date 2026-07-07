<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orang') || ! Schema::hasColumn('orang', 'birth_day_month')) {
            return;
        }

        Schema::table('orang', function (Blueprint $table): void {
            $table->dropColumn('birth_day_month');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orang') || Schema::hasColumn('orang', 'birth_day_month')) {
            return;
        }

        Schema::table('orang', function (Blueprint $table): void {
            $table->string('birth_day_month')->nullable();
        });
    }
};
