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

        Schema::table('public_material_files', function (Blueprint $table): void {
            if (! Schema::hasColumn('public_material_files', 'text_content')) {
                $table->longText('text_content')->nullable();
            }
            if (! Schema::hasColumn('public_material_files', 'text_extracted_at')) {
                $table->timestamp('text_extracted_at')->nullable();
            }
            if (! Schema::hasColumn('public_material_files', 'text_extraction_error')) {
                $table->text('text_extraction_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('public_material_files')) {
            return;
        }

        Schema::table('public_material_files', function (Blueprint $table): void {
            foreach (['text_content', 'text_extracted_at', 'text_extraction_error'] as $column) {
                if (Schema::hasColumn('public_material_files', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
