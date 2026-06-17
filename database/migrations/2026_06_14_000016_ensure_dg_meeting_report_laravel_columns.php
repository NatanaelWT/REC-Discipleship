<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discipleship_meeting_reports')) {
            Schema::table('discipleship_meeting_reports', function (Blueprint $table): void {
                $this->stringColumn($table, 'public_id', 120);
                $this->stringColumn($table, 'branch_code', 40);
                $this->unsignedBigIntegerColumn($table, 'leader_person_id');
                $this->stringColumn($table, 'leader_person_public_id', 120);
                $this->stringColumn($table, 'leader_name_snapshot');
                $this->unsignedBigIntegerColumn($table, 'discipleship_group_id');
                $this->stringColumn($table, 'discipleship_group_public_id', 120);
                $this->stringColumn($table, 'group_name_snapshot');
                $this->dateColumn($table, 'meeting_date');
                $this->stringColumn($table, 'material_topic');
                $this->stringColumn($table, 'group_progress_snapshot', 80);
                $this->textColumn($table, 'absence_reason');
                $this->longTextColumn($table, 'additional_notes');
                $this->unsignedTinyIntegerColumn($table, 'meditation_min_times', 0);
                $this->unsignedTinyIntegerColumn($table, 'sharing_openness_score');
                $this->booleanColumn($table, 'prepared_material', false);
                $this->booleanColumn($table, 'prayed_for_members', false);
                $this->booleanColumn($table, 'shared_meditation', false);
                $this->booleanColumn($table, 'relationally_contacted', false);
                $this->stringColumn($table, 'source', 80, false, 'public_form');
                $this->timestamps($table, 'discipleship_meeting_reports');
            });
        }

        if (Schema::hasTable('discipleship_meeting_report_absences')) {
            Schema::table('discipleship_meeting_report_absences', function (Blueprint $table): void {
                $this->unsignedBigIntegerColumn($table, 'discipleship_meeting_report_id', false);
                $this->unsignedBigIntegerColumn($table, 'person_id');
                $this->stringColumn($table, 'person_public_id', 120);
                $this->stringColumn($table, 'person_name_snapshot');
                $this->timestamps($table, 'discipleship_meeting_report_absences');
            });
        }

        if (Schema::hasTable('discipleship_meeting_report_meditation_sharers')) {
            Schema::table('discipleship_meeting_report_meditation_sharers', function (Blueprint $table): void {
                $this->unsignedBigIntegerColumn($table, 'discipleship_meeting_report_id', false);
                $this->unsignedBigIntegerColumn($table, 'person_id');
                $this->stringColumn($table, 'person_public_id', 120);
                $this->stringColumn($table, 'person_name_snapshot');
                $this->timestamps($table, 'discipleship_meeting_report_meditation_sharers');
            });
        }

        if (Schema::hasTable('discipleship_meeting_report_photos')) {
            Schema::table('discipleship_meeting_report_photos', function (Blueprint $table): void {
                $this->unsignedBigIntegerColumn($table, 'discipleship_meeting_report_id', false);
                $this->stringColumn($table, 'relative_path', 500, false);
                $this->stringColumn($table, 'original_file_name');
                $this->unsignedSmallIntegerColumn($table, 'sort_order', 0);
                $this->timestamps($table, 'discipleship_meeting_report_photos');
            });
        }
    }

    public function down(): void
    {
        // Compatibility guard only. These columns belong to the canonical
        // Laravel meeting-report migrations and must not be dropped here.
    }

    private function stringColumn(Blueprint $table, string $column, int $length = 255, bool $nullable = true, ?string $default = null): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $definition = $table->string($column, $length);
        if ($nullable) {
            $definition->nullable();
        }
        if ($default !== null) {
            $definition->default($default);
        }
    }

    private function unsignedBigIntegerColumn(Blueprint $table, string $column, bool $nullable = true): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $definition = $table->unsignedBigInteger($column);
        if ($nullable) {
            $definition->nullable();
        }
    }

    private function unsignedTinyIntegerColumn(Blueprint $table, string $column, ?int $default = null): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $definition = $table->unsignedTinyInteger($column);
        if ($default !== null) {
            $definition->default($default);
        } else {
            $definition->nullable();
        }
    }

    private function unsignedSmallIntegerColumn(Blueprint $table, string $column, int $default): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $table->unsignedSmallInteger($column)->default($default);
    }

    private function booleanColumn(Blueprint $table, string $column, bool $default): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $table->boolean($column)->default($default);
    }

    private function dateColumn(Blueprint $table, string $column): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $table->date($column)->nullable();
    }

    private function textColumn(Blueprint $table, string $column): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $table->text($column)->nullable();
    }

    private function longTextColumn(Blueprint $table, string $column): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $table->longText($column)->nullable();
    }

    private function timestamps(Blueprint $table, string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'created_at') && ! Schema::hasColumn($tableName, 'updated_at')) {
            $table->timestamps();

            return;
        }

        if (! Schema::hasColumn($tableName, 'created_at')) {
            $table->timestamp('created_at')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'updated_at')) {
            $table->timestamp('updated_at')->nullable();
        }
    }
};
