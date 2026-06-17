<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureDiscipleshipTargets();
        $this->ensureMskParticipants();
        $this->ensureMskParticipantSessions();
        $this->ensureMskParticipantPhotos();
    }

    public function down(): void
    {
        // Guard migration only. It does not remove normalized dashboard data.
    }

    private function ensureDiscipleshipTargets(): void
    {
        if (! Schema::hasTable('discipleship_targets')) {
            Schema::create('discipleship_targets', function (Blueprint $table): void {
                $table->id();
                $table->string('branch_code', 40)->unique();
                $table->unsignedInteger('camp_gap_participant_target')->default(50);
                $table->unsignedInteger('msk_completion_target')->default(50);
                $table->unsignedInteger('dg1_completion_target')->default(50);
                $table->unsignedInteger('dg2_completion_target')->default(50);
                $table->unsignedInteger('dg3_completion_target')->default(50);
                $table->timestamps();
            });

            return;
        }

        Schema::table('discipleship_targets', function (Blueprint $table): void {
            $this->string($table, 'branch_code', 40);
            $this->unsignedInteger($table, 'camp_gap_participant_target', 50);
            $this->unsignedInteger($table, 'msk_completion_target', 50);
            $this->unsignedInteger($table, 'dg1_completion_target', 50);
            $this->unsignedInteger($table, 'dg2_completion_target', 50);
            $this->unsignedInteger($table, 'dg3_completion_target', 50);
            $this->timestamps($table, 'discipleship_targets');
        });
    }

    private function ensureMskParticipants(): void
    {
        if (! Schema::hasTable('msk_participants')) {
            Schema::create('msk_participants', function (Blueprint $table): void {
                $table->id();
                $table->string('public_id', 120);
                $table->string('branch_code', 40);
                $table->string('member_public_id', 120)->nullable();
                $table->string('full_name')->nullable();
                $table->string('gender', 40)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('birth_day_month', 20)->nullable();
                $table->string('birth_place', 120)->nullable();
                $table->text('address')->nullable();
                $table->string('email')->nullable();
                $table->string('whatsapp', 80)->nullable();
                $table->string('batch_month', 20)->nullable();
                $table->text('notes')->nullable();
                $table->string('completed_at', 80)->nullable();
                $table->string('journey_bridge_status', 80)->default('belum');
                $table->string('status', 80)->default('active');
                $table->timestamps();

                $table->unique(['branch_code', 'public_id'], 'msk_participants_branch_public_unique');
                $table->index('branch_code', 'msk_participants_branch_index');
                $table->index('public_id', 'msk_participants_public_index');
            });

            return;
        }

        Schema::table('msk_participants', function (Blueprint $table): void {
            $this->string($table, 'public_id', 120);
            $this->string($table, 'branch_code', 40);
            $this->nullableString($table, 'member_public_id', 120);
            $this->nullableString($table, 'full_name');
            $this->nullableString($table, 'gender', 40);
            $this->nullableDate($table, 'birth_date');
            $this->nullableString($table, 'birth_day_month', 20);
            $this->nullableString($table, 'birth_place', 120);
            $this->nullableText($table, 'address');
            $this->nullableString($table, 'email');
            $this->nullableString($table, 'whatsapp', 80);
            $this->nullableString($table, 'batch_month', 20);
            $this->nullableText($table, 'notes');
            $this->nullableString($table, 'completed_at', 80);
            $this->string($table, 'journey_bridge_status', 80, 'belum');
            $this->string($table, 'status', 80, 'active');
            $this->timestamps($table, 'msk_participants');
        });
    }

    private function ensureMskParticipantSessions(): void
    {
        if (! Schema::hasTable('msk_participant_sessions')) {
            Schema::create('msk_participant_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('msk_participant_id')->constrained('msk_participants')->cascadeOnDelete();
                $table->unsignedTinyInteger('session_number');
                $table->timestamps();

                $table->unique(['msk_participant_id', 'session_number'], 'msk_sessions_participant_number_unique');
            });

            return;
        }

        Schema::table('msk_participant_sessions', function (Blueprint $table): void {
            $this->unsignedBigInteger($table, 'msk_participant_id');
            $this->unsignedTinyInteger($table, 'session_number');
            $this->timestamps($table, 'msk_participant_sessions');
        });
    }

    private function ensureMskParticipantPhotos(): void
    {
        if (! Schema::hasTable('msk_participant_photos')) {
            Schema::create('msk_participant_photos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('msk_participant_id')->constrained('msk_participants')->cascadeOnDelete();
                $table->string('path', 500);
                $table->string('original_name')->nullable();
                $table->timestamps();

                $table->unique(['msk_participant_id', 'path'], 'msk_photos_participant_path_unique');
            });

            return;
        }

        Schema::table('msk_participant_photos', function (Blueprint $table): void {
            $this->unsignedBigInteger($table, 'msk_participant_id');
            $this->string($table, 'path', 500);
            $this->nullableString($table, 'original_name');
            $this->timestamps($table, 'msk_participant_photos');
        });
    }

    private function string(Blueprint $table, string $column, int $length = 255, ?string $default = null): void
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return;
        }

        $definition = $table->string($column, $length);
        if ($default !== null) {
            $definition->default($default);
        }
    }

    private function nullableString(Blueprint $table, string $column, int $length = 255): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $table->string($column, $length)->nullable();
        }
    }

    private function nullableText(Blueprint $table, string $column): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $table->text($column)->nullable();
        }
    }

    private function nullableDate(Blueprint $table, string $column): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $table->date($column)->nullable();
        }
    }

    private function unsignedInteger(Blueprint $table, string $column, int $default): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $table->unsignedInteger($column)->default($default);
        }
    }

    private function unsignedBigInteger(Blueprint $table, string $column): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $table->unsignedBigInteger($column);
        }
    }

    private function unsignedTinyInteger(Blueprint $table, string $column): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $table->unsignedTinyInteger($column);
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
