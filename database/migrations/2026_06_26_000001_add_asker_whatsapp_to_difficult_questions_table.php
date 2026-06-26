<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('difficult_questions') || Schema::hasColumn('difficult_questions', 'asker_whatsapp')) {
            return;
        }

        Schema::table('difficult_questions', static function (Blueprint $table): void {
            $table->string('asker_whatsapp', 80)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('difficult_questions') || ! Schema::hasColumn('difficult_questions', 'asker_whatsapp')) {
            return;
        }

        Schema::table('difficult_questions', static function (Blueprint $table): void {
            $table->dropColumn('asker_whatsapp');
        });
    }
};
