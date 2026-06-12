<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $recTables = [
        'rec_users',
        'rec_church_files',
        'rec_people_registry',
        'rec_discipleship_groups',
        'rec_discipleship_relationships',
        'rec_dg_meeting_reports',
        'rec_dg_member_feedback_journals',
        'rec_discipleship_targets',
        'rec_worship_penatalayan_schedules',
        'rec_login_attempts',
        'rec_difficult_questions',
    ];

    /**
     * @var list<string>
     */
    private array $metadataColumns = [
        'document_schema_version',
        'document_name',
        'document_updated_at',
        'document_branches',
        'sort_order',
        'legacy_id',
        'payload',
        'payload_checksum',
        'source_updated_at',
    ];

    /**
     * @var list<string>
     */
    private array $documentTables = [
        'rec_people_registry',
        'rec_discipleship_groups',
        'rec_discipleship_relationships',
        'rec_dg_meeting_reports',
        'rec_dg_member_feedback_journals',
        'rec_discipleship_targets',
    ];

    public function up(): void
    {
        foreach ($this->recTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $this->dropLegacyIdIndex($tableName);

            $columnsToDrop = array_values(array_filter(
                $this->metadataColumns,
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

    public function down(): void
    {
        foreach ($this->recTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (in_array($tableName, $this->documentTables, true)) {
                    $this->addColumnIfMissing($table, $tableName, 'document_schema_version', static fn (Blueprint $table) => $table->unsignedInteger('document_schema_version')->nullable());
                    $this->addColumnIfMissing($table, $tableName, 'document_name', static fn (Blueprint $table) => $table->string('document_name', 120)->nullable());
                    $this->addColumnIfMissing($table, $tableName, 'document_updated_at', static fn (Blueprint $table) => $table->string('document_updated_at', 80)->nullable());
                    $this->addColumnIfMissing($table, $tableName, 'document_branches', static fn (Blueprint $table) => $table->longText('document_branches')->nullable());
                }

                $this->addColumnIfMissing($table, $tableName, 'sort_order', static fn (Blueprint $table) => $table->unsignedInteger('sort_order')->default(0));
                $this->addColumnIfMissing($table, $tableName, 'legacy_id', static fn (Blueprint $table) => $table->string('legacy_id', 120)->nullable());
                $this->addColumnIfMissing($table, $tableName, 'payload', static fn (Blueprint $table) => $table->longText('payload')->nullable());
                $this->addColumnIfMissing($table, $tableName, 'payload_checksum', static fn (Blueprint $table) => $table->string('payload_checksum', 64)->default(''));
                $this->addColumnIfMissing($table, $tableName, 'source_updated_at', static fn (Blueprint $table) => $table->timestamp('source_updated_at')->nullable());
            });

            $this->addLegacyIdIndex($tableName);
        }
    }

    private function dropLegacyIdIndex(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'legacy_id')) {
            return;
        }

        try {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropIndex(['legacy_id']);
            });
        } catch (\Throwable) {
            // Some databases or already-modified schemas may not have this index.
        }
    }

    private function addLegacyIdIndex(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'legacy_id')) {
            return;
        }

        try {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->index('legacy_id');
            });
        } catch (\Throwable) {
            // Avoid failing rollback if the index already exists.
        }
    }

    private function addColumnIfMissing(Blueprint $table, string $tableName, string $column, callable $definition): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            $definition($table);
        }
    }
};
