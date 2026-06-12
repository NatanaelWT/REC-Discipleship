<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_files', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->unique();
            $table->string('title')->nullable();
            $table->string('category_name', 120)->nullable()->index();
            $table->longText('description')->nullable();
            $table->string('relative_path', 500)->index();
            $table->string('original_file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 180)->nullable();
            $table->string('branch_code', 40)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('public_material_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('menu_key', 120)->unique();
            $table->string('label');
            $table->string('subtitle')->nullable();
            $table->string('folder_path', 500)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('public_material_menu_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('public_material_menu_id');
            $table->unsignedBigInteger('church_file_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['public_material_menu_id', 'church_file_id'],
                'public_material_menu_files_unique'
            );
            $table->index('sort_order', 'public_material_menu_files_sort_index');
            $table->foreign('public_material_menu_id', 'public_material_menu_files_menu_fk')
                ->references('id')->on('public_material_menus')->cascadeOnDelete();
            $table->foreign('church_file_id', 'public_material_menu_files_file_fk')
                ->references('id')->on('church_files')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_material_menu_files');
        Schema::dropIfExists('public_material_menus');
        Schema::dropIfExists('church_files');
    }
};
