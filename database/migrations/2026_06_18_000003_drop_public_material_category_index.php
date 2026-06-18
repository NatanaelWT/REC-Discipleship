<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_material_files')) {
            return;
        }

        $this->dropIndexIfPossible('public_material_files', 'public_material_files_category_name_index');
    }

    public function down(): void
    {
        if (! Schema::hasTable('public_material_files') || ! Schema::hasColumn('public_material_files', 'category_name')) {
            return;
        }

        try {
            Schema::table('public_material_files', function (Blueprint $table): void {
                $table->index('category_name', 'public_material_files_category_name_index');
            });
        } catch (Throwable) {
            // Existing databases may already have this legacy index.
        }
    }

    private function dropIndexIfPossible(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        } catch (Throwable) {
            // The index is absent on databases already created with the final schema.
        }
    }
};
