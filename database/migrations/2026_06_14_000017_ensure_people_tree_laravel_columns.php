<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureDiscipleshipPeople();
        $this->ensureDiscipleshipGroups();
        $this->ensureDiscipleshipRelationships();
        $this->ensureDiscipleshipGroupMemberships();
        $this->ensureDiscipleshipGroupLeaderships();
        $this->ensureDiscipleshipGroupMultiplications();
    }

    public function down(): void
    {
        // Guard migration only. It does not remove normalized data columns.
    }

    private function ensureDiscipleshipPeople(): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        Schema::table('discipleship_people', function (Blueprint $table): void {
            $this->string($table, 'public_id', 120);
            $this->string($table, 'branch_code', 40);
            $this->nullableString($table, 'member_public_id', 120);
            $this->nullableString($table, 'full_name');
            $this->nullableString($table, 'phone', 80);
            $this->nullableString($table, 'gender', 40);
            $this->string($table, 'status', 80, 'active');
            $this->nullableLongText($table, 'notes');
            $this->timestamps($table, 'discipleship_people');
        });
    }

    private function ensureDiscipleshipGroups(): void
    {
        if (! Schema::hasTable('discipleship_groups')) {
            return;
        }

        Schema::table('discipleship_groups', function (Blueprint $table): void {
            $this->string($table, 'public_id', 120);
            $this->string($table, 'branch_code', 40);
            $this->string($table, 'name', 255, 'Kelompok');
            $this->string($table, 'status', 80, 'active');
            $this->nullableString($table, 'start_stage', 80);
            $this->nullableString($table, 'current_stage', 80);
            if (! Schema::hasColumn('discipleship_groups', 'parent_group_id')) {
                $table->unsignedBigInteger('parent_group_id')->nullable();
            }
            $this->nullableString($table, 'parent_group_public_id', 120);
            $this->nullableLongText($table, 'notes');
            $this->timestamps($table, 'discipleship_groups');
        });
    }

    private function ensureDiscipleshipRelationships(): void
    {
        if (! Schema::hasTable('discipleship_relationships')) {
            return;
        }

        Schema::table('discipleship_relationships', function (Blueprint $table): void {
            $this->nullableString($table, 'public_id', 120);
            $this->string($table, 'branch_code', 40);
            $this->nullableUnsignedBigInteger($table, 'mentor_person_id');
            $this->nullableString($table, 'mentor_person_public_id', 120);
            $this->nullableUnsignedBigInteger($table, 'disciple_person_id');
            $this->nullableString($table, 'disciple_person_public_id', 120);
            $this->nullableUnsignedBigInteger($table, 'context_group_id');
            $this->nullableString($table, 'context_group_public_id', 120);
            $this->nullableString($table, 'relation_type', 120);
            $this->nullableString($table, 'stage_at_start', 80);
            $this->string($table, 'status', 80, 'active');
            $this->nullableDate($table, 'start_date');
            $this->nullableDate($table, 'end_date');
            $this->nullableString($table, 'reason_end');
            $this->nullableLongText($table, 'notes');
            $this->timestamps($table, 'discipleship_relationships');
        });
    }

    private function ensureDiscipleshipGroupMemberships(): void
    {
        if (! Schema::hasTable('discipleship_group_memberships')) {
            return;
        }

        Schema::table('discipleship_group_memberships', function (Blueprint $table): void {
            $this->nullableString($table, 'public_id', 120);
            $this->string($table, 'branch_code', 40);
            $this->unsignedBigInteger($table, 'discipleship_group_id');
            $this->string($table, 'group_public_id', 120);
            $this->nullableUnsignedBigInteger($table, 'person_id');
            $this->nullableString($table, 'person_public_id', 120);
            $this->string($table, 'role', 80, 'member');
            $this->nullableString($table, 'stage', 80);
            $this->string($table, 'status', 80, 'active');
            $this->nullableDate($table, 'start_date');
            $this->nullableDate($table, 'end_date');
            $this->nullableString($table, 'reason_end');
            $this->timestamps($table, 'discipleship_group_memberships');
        });
    }

    private function ensureDiscipleshipGroupLeaderships(): void
    {
        if (! Schema::hasTable('discipleship_group_leaderships')) {
            return;
        }

        Schema::table('discipleship_group_leaderships', function (Blueprint $table): void {
            $this->nullableString($table, 'public_id', 120);
            $this->string($table, 'branch_code', 40);
            $this->unsignedBigInteger($table, 'discipleship_group_id');
            $this->string($table, 'group_public_id', 120);
            $this->nullableUnsignedBigInteger($table, 'person_id');
            $this->nullableString($table, 'person_public_id', 120);
            $this->string($table, 'role', 80, 'leader');
            $this->string($table, 'status', 80, 'active');
            $this->nullableDate($table, 'start_date');
            $this->nullableDate($table, 'end_date');
            $this->nullableString($table, 'reason_change');
            $this->timestamps($table, 'discipleship_group_leaderships');
        });
    }

    private function ensureDiscipleshipGroupMultiplications(): void
    {
        if (! Schema::hasTable('discipleship_group_multiplications')) {
            return;
        }

        Schema::table('discipleship_group_multiplications', function (Blueprint $table): void {
            $this->nullableString($table, 'public_id', 120);
            $this->string($table, 'branch_code', 40);
            $this->nullableUnsignedBigInteger($table, 'initiated_by_person_id');
            $this->nullableString($table, 'initiated_by_person_public_id', 120);
            $this->nullableUnsignedBigInteger($table, 'source_group_id');
            $this->nullableString($table, 'source_group_public_id', 120);
            $this->nullableUnsignedBigInteger($table, 'new_group_id');
            $this->nullableString($table, 'new_group_public_id', 120);
            $this->nullableDate($table, 'multiplication_date');
            $this->nullableLongText($table, 'notes');
            $this->timestamps($table, 'discipleship_group_multiplications');
        });
    }

    private function string(Blueprint $table, string $tableColumn, int $length = 255, ?string $default = null): void
    {
        if (Schema::hasColumn($table->getTable(), $tableColumn)) {
            return;
        }

        $column = $table->string($tableColumn, $length);
        if ($default !== null) {
            $column->default($default);
        }
    }

    private function nullableString(Blueprint $table, string $tableColumn, int $length = 255): void
    {
        if (! Schema::hasColumn($table->getTable(), $tableColumn)) {
            $table->string($tableColumn, $length)->nullable();
        }
    }

    private function nullableLongText(Blueprint $table, string $tableColumn): void
    {
        if (! Schema::hasColumn($table->getTable(), $tableColumn)) {
            $table->longText($tableColumn)->nullable();
        }
    }

    private function nullableUnsignedBigInteger(Blueprint $table, string $tableColumn): void
    {
        if (! Schema::hasColumn($table->getTable(), $tableColumn)) {
            $table->unsignedBigInteger($tableColumn)->nullable();
        }
    }

    private function unsignedBigInteger(Blueprint $table, string $tableColumn): void
    {
        if (! Schema::hasColumn($table->getTable(), $tableColumn)) {
            $table->unsignedBigInteger($tableColumn);
        }
    }

    private function nullableDate(Blueprint $table, string $tableColumn): void
    {
        if (! Schema::hasColumn($table->getTable(), $tableColumn)) {
            $table->date($tableColumn)->nullable();
        }
    }

    private function timestamps(Blueprint $table, string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'created_at')) {
            $table->timestamp('created_at')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'updated_at')) {
            $table->timestamp('updated_at')->nullable();
        }
    }
};
