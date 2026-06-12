<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $columnsByTable = [
        'rec_church_files' => [
            'record_uid' => 'string_index',
            'uploaded_at_text' => 'short_string',
            'updated_at_text' => 'short_string',
        ],
        'rec_people_registry' => [
            'record_uid' => 'string_index',
            'address' => 'text',
            'birth_date' => 'date_string',
            'birth_day_month' => 'month_string',
            'birth_place' => 'medium_string',
            'gender' => 'small_string',
            'left_at' => 'short_string',
            'left_reason' => 'text',
            'family_ids_json' => 'json_text',
            'photos_json' => 'json_text',
            'social_media' => 'text',
            'msk_status' => 'small_string',
            'msk_completed_at' => 'short_string',
            'msk_journey_bridge_status' => 'small_string',
            'msk_notes' => 'text',
            'msk_session_numbers_json' => 'json_text',
            'dg_person_id' => 'string_index',
            'dg_member_ref' => 'string_index',
            'dg_status' => 'small_string',
            'dg_notes' => 'text',
            'dg_created_at' => 'short_string',
            'dg_updated_at' => 'short_string',
            'legacy_dg_person_id' => 'string_index',
            'legacy_dg_role' => 'small_string',
            'legacy_dg_parent_ids_json' => 'json_text',
            'legacy_dg_notes' => 'text',
            'legacy_dg_created_at' => 'short_string',
            'legacy_dg_updated_at' => 'short_string',
            'record_updated_at' => 'short_string',
        ],
        'rec_discipleship_groups' => [
            'record_uid' => 'string_index',
            'record_updated_at' => 'short_string',
        ],
        'rec_discipleship_relationships' => [
            'record_uid' => 'string_index',
            'initiated_by_person_id' => 'string_index',
            'leader_person_id' => 'string_index',
            'source_group_id' => 'string_index',
            'new_group_id' => 'string_index',
            'relation_type' => 'small_string',
            'stage_at_start' => 'small_string',
            'multiplication_date' => 'date_string',
            'notes' => 'text',
            'reason_change' => 'medium_string',
            'reason_close' => 'medium_string',
            'reason_end' => 'medium_string',
            'record_created_at' => 'short_string',
            'record_updated_at' => 'short_string',
        ],
        'rec_dg_meeting_reports' => [
            'record_uid' => 'string_index',
            'absence_reason' => 'string',
            'absent_member_ids_json' => 'json_text',
            'additional_notes' => 'text',
            'meditation_min_times' => 'unsigned_integer',
            'meditation_sharer_ids_json' => 'json_text',
            'meeting_photos_json' => 'json_text',
            'quality_pray' => 'small_string',
            'quality_prepare' => 'small_string',
            'quality_relational' => 'small_string',
            'quality_share_meditation' => 'small_string',
            'sharing_openness' => 'unsigned_integer',
            'record_updated_at' => 'short_string',
        ],
        'rec_dg_member_feedback_journals' => [
            'record_uid' => 'string_index',
            'leader_name' => 'string',
            'group_label' => 'string',
            'group_name' => 'string',
            'notes_json' => 'json_text',
            'ratings_json' => 'json_text',
            'record_created_at' => 'short_string',
            'record_updated_at' => 'short_string',
        ],
        'rec_worship_penatalayan_schedules' => [
            'record_updated_at' => 'short_string',
        ],
        'rec_difficult_questions' => [
            'record_uid' => 'string_index',
            'question' => 'json_text',
            'password_hash' => 'string',
            'answer' => 'json_text',
            'record_updated_at' => 'short_string',
        ],
    ];

    public function up(): void
    {
        foreach ($this->columnsByTable as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as $column => $type) {
                    if (Schema::hasColumn($tableName, $column)) {
                        continue;
                    }

                    $this->addColumn($table, $column, $type);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columnsByTable as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columnsToDrop = array_values(array_filter(
                array_keys($columns),
                static fn (string $column): bool => Schema::hasColumn($tableName, $column),
            ));

            if ($columnsToDrop === []) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function addColumn(Blueprint $table, string $column, string $type): void
    {
        match ($type) {
            'string_index' => $table->string($column, 120)->nullable()->index(),
            'short_string' => $table->string($column, 80)->nullable(),
            'medium_string' => $table->string($column, 120)->nullable(),
            'small_string' => $table->string($column, 80)->nullable(),
            'month_string' => $table->string($column, 20)->nullable(),
            'date_string' => $table->string($column, 40)->nullable(),
            'text' => $table->text($column)->nullable(),
            'json_text' => $table->longText($column)->nullable(),
            'unsigned_integer' => $table->unsignedInteger($column)->default(0),
            default => $table->string($column)->nullable(),
        };
    }
};
