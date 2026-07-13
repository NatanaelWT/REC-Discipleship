<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const ACTIVE_STATUSES = ['pending', 'running'];

    /** @var list<string> */
    private const DROP_ORDER = [
        'msk_import_batches',
        'msk_import_existing_people',
        'msk_import_source_keys',
        'msk_import_jobs',
    ];

    public function up(): void
    {
        if (Schema::hasTable('msk_import_jobs')) {
            if (! Schema::hasColumn('msk_import_jobs', 'status')) {
                throw new RuntimeException(
                    'Tabel msk_import_jobs tidak memiliki kolom status; keamanan penghapusan job aktif tidak dapat diverifikasi.',
                );
            }

            $hasActiveBranch = Schema::hasColumn('msk_import_jobs', 'active_branch_id');
            $activeJobs = DB::table('msk_import_jobs')
                ->where(function ($query) use ($hasActiveBranch): void {
                    $query->whereIn('status', self::ACTIVE_STATUSES)
                        ->orWhereNull('status')
                        ->orWhereNotIn('status', ['completed', 'failed']);
                    if ($hasActiveBranch) {
                        $query->orWhereNotNull('active_branch_id');
                    }
                })
                ->count();
            if ($activeJobs > 0) {
                throw new RuntimeException(
                    "Penghapusan tabel import MSK dibatalkan: {$activeJobs} job non-terminal/aktif masih ada.",
                );
            }
        }

        foreach (self::DROP_ORDER as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        Schema::create('msk_import_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('active_branch_id')->nullable()->unique();
            $table->string('idempotency_token', 100);
            $table->string('status', 20)->default('pending');
            $table->string('source_name')->nullable();
            $table->string('source_path')->nullable();
            $table->string('staged_path')->nullable();
            $table->string('source_sha256', 64)->nullable();
            $table->unsignedBigInteger('source_size')->default(0);
            $table->unsignedBigInteger('staged_byte_cursor')->default(0);
            $table->unsignedInteger('row_cursor')->default(0);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('inserted_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->json('errors')->nullable();
            $table->string('lock_token', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('last_batch_token', 100)->nullable();
            $table->json('last_batch_result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id', 'idempotency_token'], 'msk_import_jobs_idempotency_unique');
            $table->index(['branch_id', 'status', 'created_at'], 'msk_import_jobs_branch_status_index');
        });

        Schema::create('msk_import_source_keys', function (Blueprint $table): void {
            $table->id();
            $table->ulid('job_id');
            $table->unsignedInteger('row_number');
            $table->string('match_type', 12);
            $table->string('match_key', 64);

            $table->unique(['job_id', 'match_type', 'match_key'], 'msk_import_source_key_unique');
            $table->foreign('job_id')->references('id')->on('msk_import_jobs')->cascadeOnDelete();
        });

        Schema::create('msk_import_existing_people', function (Blueprint $table): void {
            $table->id();
            $table->ulid('job_id');
            $table->unsignedBigInteger('person_id');
            $table->string('identity_key', 64)->nullable();
            $table->timestamp('touched_at')->nullable();

            $table->unique(['job_id', 'person_id'], 'msk_import_existing_person_unique');
            $table->index(['job_id', 'identity_key'], 'msk_import_existing_identity_index');
            $table->foreign('job_id')->references('id')->on('msk_import_jobs')->cascadeOnDelete();
        });

        Schema::create('msk_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('job_id');
            $table->string('batch_token', 100);
            $table->unsignedBigInteger('byte_cursor_before');
            $table->unsignedBigInteger('byte_cursor_after');
            $table->unsignedInteger('row_count')->default(0);
            $table->json('result');
            $table->timestamps();

            $table->unique(['job_id', 'batch_token'], 'msk_import_batch_token_unique');
            $table->foreign('job_id')->references('id')->on('msk_import_jobs')->cascadeOnDelete();
        });
    }
};
