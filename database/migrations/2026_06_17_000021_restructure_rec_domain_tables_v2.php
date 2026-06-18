<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createBranches();
        $this->seedBranches();
        $this->addBranchIds();

        $this->createDiscipleshipGroupPeople();
        $this->addDiscipleshipGroupMultiplicationColumns();
        $this->addMskJsonColumns();
        $this->addMeetingReportJsonColumns();
        $this->createDiscipleshipFeedbacks();
        $this->createPublicMaterialFiles();
        $this->createWorshipSchedules();

        $this->backfillMskJson();
        $this->backfillMeetingReportJson();
        $this->backfillDiscipleshipGroupPeople();
        $this->backfillDiscipleshipGroupMultiplications();
        $this->backfillDiscipleshipFeedbacks();
        $this->backfillPublicMaterialFiles();
        $this->backfillWorshipSchedules();
    }

    public function down(): void
    {
        Schema::dropIfExists('worship_schedules');
        Schema::dropIfExists('public_material_files');
        Schema::dropIfExists('discipleship_feedbacks');
        Schema::dropIfExists('discipleship_group_people');

        $this->dropColumnIfExists('msk_participants', 'session_numbers');
        $this->dropColumnIfExists('msk_participants', 'photos');
        $this->dropColumnIfExists('discipleship_meeting_reports', 'absences');
        $this->dropColumnIfExists('discipleship_meeting_reports', 'meditation_sharers');
        $this->dropColumnIfExists('discipleship_meeting_reports', 'photos');
        $this->dropColumnIfExists('discipleship_groups', 'source_group_id');
        $this->dropColumnIfExists('discipleship_groups', 'source_group_public_id');
        $this->dropColumnIfExists('discipleship_groups', 'initiated_by_person_id');
        $this->dropColumnIfExists('discipleship_groups', 'initiated_by_person_public_id');
        $this->dropColumnIfExists('discipleship_groups', 'multiplied_at');

        foreach ($this->branchScopedTables() as $table) {
            $this->dropColumnIfExists($table, 'branch_id');
        }

        Schema::dropIfExists('branches');
    }

    private function createBranches(): void
    {
        if (Schema::hasTable('branches')) {
            return;
        }

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function seedBranches(): void
    {
        $branches = [
            'kutisari' => 'Kutisari',
            'gm' => 'GM',
            'darmo' => 'Darmo',
            'merr' => 'Merr',
            'batam' => 'Batam',
            'nginden' => 'Nginden',
        ];

        foreach ($this->branchScopedTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_code')) {
                continue;
            }

            foreach (DB::table($table)->whereNotNull('branch_code')->distinct()->pluck('branch_code') as $code) {
                $code = strtolower(trim((string) $code));
                if ($code !== '' && ! isset($branches[$code])) {
                    $branches[$code] = strtoupper($code);
                }
            }
        }

        $now = now();
        $sortOrder = 0;
        foreach ($branches as $code => $label) {
            DB::table('branches')->updateOrInsert(
                ['code' => $code],
                [
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $sortOrder++;
        }
    }

    private function addBranchIds(): void
    {
        foreach ($this->branchScopedTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_code') || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable()->after('branch_code')->constrained('branches')->nullOnDelete();
            });
        }

        $branchIds = DB::table('branches')->pluck('id', 'code')->all();
        foreach ($this->branchScopedTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id') || ! Schema::hasColumn($table, 'branch_code')) {
                continue;
            }

            foreach ($branchIds as $code => $id) {
                DB::table($table)
                    ->where('branch_code', $code)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $id]);
            }
        }
    }

    private function createDiscipleshipGroupPeople(): void
    {
        if (Schema::hasTable('discipleship_group_people')) {
            return;
        }

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('branch_code', 40)->nullable()->index();
            $table->foreignId('discipleship_group_id')->constrained('discipleship_groups')->cascadeOnDelete();
            $table->string('group_public_id', 120);
            $table->foreignId('person_id')->nullable()->constrained('discipleship_people')->nullOnDelete();
            $table->string('person_public_id', 120)->nullable();
            $table->string('role', 80)->default('member');
            $table->string('stage', 80)->nullable();
            $table->string('status', 80)->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'public_id'], 'dg_group_people_branch_public_unique');
            $table->index('group_public_id', 'dg_group_people_group_public_index');
            $table->index('person_public_id', 'dg_group_people_person_public_index');
            $table->index(['branch_id', 'role', 'status'], 'dg_group_people_branch_role_status_index');
        });
    }

    private function addDiscipleshipGroupMultiplicationColumns(): void
    {
        if (! Schema::hasTable('discipleship_groups')) {
            return;
        }

        Schema::table('discipleship_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('discipleship_groups', 'source_group_id')) {
                $table->foreignId('source_group_id')->nullable()->after('parent_group_public_id')->constrained('discipleship_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('discipleship_groups', 'source_group_public_id')) {
                $table->string('source_group_public_id', 120)->nullable()->after('source_group_id')->index('discipleship_groups_source_public_index');
            }
            if (! Schema::hasColumn('discipleship_groups', 'initiated_by_person_id')) {
                $table->foreignId('initiated_by_person_id')->nullable()->after('source_group_public_id')->constrained('discipleship_people')->nullOnDelete();
            }
            if (! Schema::hasColumn('discipleship_groups', 'initiated_by_person_public_id')) {
                $table->string('initiated_by_person_public_id', 120)->nullable()->after('initiated_by_person_id')->index('discipleship_groups_initiator_public_index');
            }
            if (! Schema::hasColumn('discipleship_groups', 'multiplied_at')) {
                $table->date('multiplied_at')->nullable()->after('initiated_by_person_public_id')->index('discipleship_groups_multiplied_at_index');
            }
        });
    }

    private function addMskJsonColumns(): void
    {
        if (! Schema::hasTable('msk_participants')) {
            return;
        }

        Schema::table('msk_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('msk_participants', 'session_numbers')) {
                $table->json('session_numbers')->nullable()->after('status');
            }
            if (! Schema::hasColumn('msk_participants', 'photos')) {
                $table->json('photos')->nullable()->after('session_numbers');
            }
        });
    }

    private function addMeetingReportJsonColumns(): void
    {
        if (! Schema::hasTable('discipleship_meeting_reports')) {
            return;
        }

        Schema::table('discipleship_meeting_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('discipleship_meeting_reports', 'absences')) {
                $table->json('absences')->nullable()->after('absence_reason');
            }
            if (! Schema::hasColumn('discipleship_meeting_reports', 'meditation_sharers')) {
                $table->json('meditation_sharers')->nullable()->after('meditation_min_times');
            }
            if (! Schema::hasColumn('discipleship_meeting_reports', 'photos')) {
                $table->json('photos')->nullable()->after('meditation_sharers');
            }
        });
    }

    private function createDiscipleshipFeedbacks(): void
    {
        if (Schema::hasTable('discipleship_feedbacks')) {
            return;
        }

        Schema::create('discipleship_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('branch_code', 40)->nullable()->index();
            $table->unsignedTinyInteger('feedback_session');
            $table->unsignedBigInteger('discipleship_group_id')->nullable()->index();
            $table->unsignedBigInteger('leader_person_id')->nullable()->index();
            $table->unsignedBigInteger('respondent_person_id')->nullable()->index();
            $table->string('respondent_name_snapshot')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->string('group_name_snapshot')->nullable();
            $table->string('group_label_snapshot')->nullable();
            $table->string('group_progress_snapshot', 80)->nullable();
            $table->json('ratings')->nullable();
            $table->json('notes')->nullable();
            $table->string('source', 80)->default('public_form');
            $table->timestamps();

            $table->index(['branch_id', 'feedback_session'], 'discipleship_feedbacks_branch_session_index');
        });
    }

    private function createPublicMaterialFiles(): void
    {
        if (Schema::hasTable('public_material_files')) {
            return;
        }

        Schema::create('public_material_files', function (Blueprint $table): void {
            $table->id();
            $table->string('menu', 80)->index();
            $table->string('public_id', 120)->unique();
            $table->string('title')->nullable();
            $table->string('category_name', 120)->nullable();
            $table->longText('description')->nullable();
            $table->string('relative_path', 500)->index();
            $table->string('original_file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 180)->nullable();
            $table->timestamps();
        });
    }

    private function createWorshipSchedules(): void
    {
        if (Schema::hasTable('worship_schedules')) {
            $this->ensureWorshipScheduleIndexes();
            return;
        }

        Schema::create('worship_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7);
            $table->string('title');
            $table->longText('update_note')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('branch_code', 40)->nullable()->index();
            $table->json('rows')->nullable();
            $table->timestamps();

            $table->unique(['branch_code', 'month'], 'worship_schedules_branch_month_unique');
            $table->index(['branch_id', 'month'], 'worship_schedules_branch_id_month_index');
        });

        $this->ensureWorshipScheduleIndexes();
    }

    private function ensureWorshipScheduleIndexes(): void
    {
        if (! Schema::hasTable('worship_schedules')) {
            return;
        }

        Schema::table('worship_schedules', function (Blueprint $table): void {
            if ($this->indexExists('worship_schedules', 'worship_schedules_month_unique')) {
                $table->dropUnique('worship_schedules_month_unique');
            }
            if ($this->indexExists('worship_schedules', 'worship_schedules_branch_month_unique')) {
                $table->dropUnique('worship_schedules_branch_month_unique');
            }
        });

        Schema::table('worship_schedules', function (Blueprint $table): void {
            if (! $this->indexExists('worship_schedules', 'worship_schedules_branch_month_unique')) {
                $table->unique(['branch_code', 'month'], 'worship_schedules_branch_month_unique');
            }
            if (! $this->indexExists('worship_schedules', 'worship_schedules_branch_id_month_index')) {
                $table->index(['branch_id', 'month'], 'worship_schedules_branch_id_month_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== [];
    }

    private function backfillMskJson(): void
    {
        if (! Schema::hasTable('msk_participants') || ! Schema::hasColumn('msk_participants', 'session_numbers')) {
            return;
        }

        $sessionsByParticipant = [];
        if (Schema::hasTable('msk_participant_sessions')) {
            foreach (DB::table('msk_participant_sessions')->select(['msk_participant_id', 'session_number'])->orderBy('session_number')->get() as $row) {
                $sessionsByParticipant[(int) $row->msk_participant_id][] = (int) $row->session_number;
            }
        }

        $photosByParticipant = [];
        if (Schema::hasTable('msk_participant_photos')) {
            foreach (DB::table('msk_participant_photos')->orderBy('id')->get() as $photo) {
                $path = trim((string) ($photo->path ?? ''));
                if ($path === '') {
                    continue;
                }

                $photosByParticipant[(int) $photo->msk_participant_id][] = [
                    'path' => $path,
                    'name' => trim((string) ($photo->original_name ?? '')) ?: 'Foto',
                ];
            }
        }

        $rows = [];
        foreach (DB::table('msk_participants')->select(['id'])->orderBy('id')->get() as $participant) {
            $sessions = array_values(array_unique(array_map('intval', $sessionsByParticipant[(int) $participant->id] ?? [])));
            sort($sessions);

            $rows[] = [
                'id' => (int) $participant->id,
                'session_numbers' => json_encode($sessions),
                'photos' => json_encode(array_values($photosByParticipant[(int) $participant->id] ?? [])),
            ];
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            if ($chunk !== []) {
                $this->bulkUpdateById('msk_participants', $chunk, ['session_numbers', 'photos']);
            }
        }
    }

    private function backfillMeetingReportJson(): void
    {
        if (! Schema::hasTable('discipleship_meeting_reports') || ! Schema::hasColumn('discipleship_meeting_reports', 'absences')) {
            return;
        }

        $absencesByReport = $this->reportPeopleRowsByReport('discipleship_meeting_report_absences');
        $sharersByReport = $this->reportPeopleRowsByReport('discipleship_meeting_report_meditation_sharers');
        $photosByReport = [];
        if (Schema::hasTable('discipleship_meeting_report_photos')) {
            foreach (DB::table('discipleship_meeting_report_photos')->orderBy('sort_order')->orderBy('id')->get() as $photo) {
                $path = trim((string) ($photo->relative_path ?? ''));
                if ($path === '') {
                    continue;
                }

                $photosByReport[(int) $photo->discipleship_meeting_report_id][] = [
                    'path' => $path,
                    'name' => trim((string) ($photo->original_file_name ?? '')) ?: basename($path),
                    'sort_order' => (int) ($photo->sort_order ?? 0),
                ];
            }
        }

        $rows = [];
        foreach (DB::table('discipleship_meeting_reports')->select(['id'])->orderBy('id')->get() as $report) {
            $reportId = (int) $report->id;
            $rows[] = [
                'id' => $reportId,
                'absences' => json_encode(array_values($absencesByReport[$reportId] ?? [])),
                'meditation_sharers' => json_encode(array_values($sharersByReport[$reportId] ?? [])),
                'photos' => json_encode(array_values($photosByReport[$reportId] ?? [])),
            ];
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            if ($chunk !== []) {
                $this->bulkUpdateById('discipleship_meeting_reports', $chunk, ['absences', 'meditation_sharers', 'photos']);
            }
        }
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function reportPeopleRowsByReport(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $rowsByReport = [];
        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            $personPublicId = trim((string) ($row->person_public_id ?? ''));
            if ($personPublicId === '') {
                continue;
            }

            $rowsByReport[(int) $row->discipleship_meeting_report_id][] = [
                'person_id' => $row->person_id !== null ? (int) $row->person_id : null,
                'person_public_id' => $personPublicId,
                'person_name_snapshot' => (string) ($row->person_name_snapshot ?? ''),
            ];
        }

        return $rowsByReport;
    }

    private function backfillDiscipleshipGroupPeople(): void
    {
        if (! Schema::hasTable('discipleship_group_people')) {
            return;
        }

        $branchIds = DB::table('branches')->pluck('id', 'code')->all();
        $rows = [];
        if (Schema::hasTable('discipleship_group_memberships')) {
            foreach (DB::table('discipleship_group_memberships')->orderBy('id')->get() as $row) {
                $rows[] = [
                    'branch_id' => $branchIds[$row->branch_code] ?? null,
                    'public_id' => $row->public_id ?: ('membership_' . $row->id),
                    'branch_code' => $row->branch_code ?? null,
                    'discipleship_group_id' => $row->discipleship_group_id,
                    'group_public_id' => $row->group_public_id,
                    'person_id' => $row->person_id,
                    'person_public_id' => $row->person_public_id,
                    'role' => $row->role ?: 'member',
                    'stage' => $row->stage,
                    'status' => $row->status ?: 'active',
                    'started_on' => $row->start_date,
                    'ended_on' => $row->end_date,
                    'end_reason' => $row->reason_end,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ];
            }
        }

        if (Schema::hasTable('discipleship_group_leaderships')) {
            foreach (DB::table('discipleship_group_leaderships')->orderBy('id')->get() as $row) {
                $rows[] = [
                    'branch_id' => $branchIds[$row->branch_code] ?? null,
                    'public_id' => $row->public_id ?: ('leadership_' . $row->id),
                    'branch_code' => $row->branch_code ?? null,
                    'discipleship_group_id' => $row->discipleship_group_id,
                    'group_public_id' => $row->group_public_id,
                    'person_id' => $row->person_id,
                    'person_public_id' => $row->person_public_id,
                    'role' => $row->role ?: 'leader',
                    'stage' => null,
                    'status' => $row->status ?: 'active',
                    'started_on' => $row->start_date,
                    'ended_on' => $row->end_date,
                    'end_reason' => $row->reason_change,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            if ($chunk !== []) {
                DB::table('discipleship_group_people')->upsert(
                    $chunk,
                    ['branch_id', 'public_id'],
                    [
                        'branch_code',
                        'discipleship_group_id',
                        'group_public_id',
                        'person_id',
                        'person_public_id',
                        'role',
                        'stage',
                        'status',
                        'started_on',
                        'ended_on',
                        'end_reason',
                        'updated_at',
                    ],
                );
            }
        }
    }

    private function backfillDiscipleshipGroupMultiplications(): void
    {
        if (! Schema::hasTable('discipleship_group_multiplications') || ! Schema::hasTable('discipleship_groups')) {
            return;
        }

        foreach (DB::table('discipleship_group_multiplications')->orderBy('id')->get() as $row) {
            $groupQuery = DB::table('discipleship_groups');
            if ($row->new_group_id !== null) {
                $groupQuery->where('id', $row->new_group_id);
            } else {
                $groupQuery->where('branch_code', $row->branch_code)->where('public_id', $row->new_group_public_id);
            }

            $groupQuery->update([
                'source_group_id' => $row->source_group_id,
                'source_group_public_id' => $row->source_group_public_id,
                'initiated_by_person_id' => $row->initiated_by_person_id,
                'initiated_by_person_public_id' => $row->initiated_by_person_public_id,
                'multiplied_at' => $row->multiplication_date,
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    private function backfillDiscipleshipFeedbacks(): void
    {
        if (! Schema::hasTable('discipleship_feedbacks') || ! Schema::hasTable('discipleship_member_feedback_journals')) {
            return;
        }

        $branchIds = DB::table('branches')->pluck('id', 'code')->all();
        foreach (DB::table('discipleship_member_feedback_journals')->orderBy('id')->get() as $journal) {
            $ratings = [];
            if (Schema::hasTable('discipleship_member_feedback_ratings')) {
                $ratings = DB::table('discipleship_member_feedback_ratings')
                    ->where('discipleship_member_feedback_journal_id', $journal->id)
                    ->orderBy('id')
                    ->get()
                    ->map(static fn (object $rating): array => [
                        'section_key' => $rating->section_key,
                        'question_key' => $rating->question_key,
                        'score' => (int) $rating->score,
                        'scale' => (int) $rating->scale,
                    ])
                    ->values()
                    ->all();
            }

            $notes = [];
            if (Schema::hasTable('discipleship_member_feedback_notes')) {
                $notes = DB::table('discipleship_member_feedback_notes')
                    ->where('discipleship_member_feedback_journal_id', $journal->id)
                    ->orderBy('id')
                    ->get()
                    ->map(static fn (object $note): array => [
                        'section_key' => $note->section_key,
                        'note_key' => $note->note_key,
                        'content' => $note->content,
                    ])
                    ->values()
                    ->all();
            }

            DB::table('discipleship_feedbacks')->updateOrInsert(
                ['public_id' => $journal->public_id],
                [
                    'branch_id' => $branchIds[$journal->branch_code] ?? null,
                    'branch_code' => $journal->branch_code,
                    'feedback_session' => $journal->feedback_session,
                    'discipleship_group_id' => $journal->discipleship_group_id,
                    'leader_person_id' => $journal->leader_person_id,
                    'respondent_person_id' => $journal->respondent_person_id,
                    'respondent_name_snapshot' => $journal->respondent_name_snapshot,
                    'leader_name_snapshot' => $journal->leader_name_snapshot,
                    'group_name_snapshot' => $journal->group_name_snapshot,
                    'group_label_snapshot' => $journal->group_label_snapshot,
                    'group_progress_snapshot' => $journal->group_progress_snapshot,
                    'ratings' => json_encode($ratings),
                    'notes' => json_encode($notes),
                    'source' => $journal->source ?: 'public_form',
                    'created_at' => $journal->created_at ?? now(),
                    'updated_at' => $journal->updated_at ?? now(),
                ],
            );
        }
    }

    private function backfillPublicMaterialFiles(): void
    {
        if (! Schema::hasTable('public_material_files') || ! Schema::hasTable('public_material_menu_files') || ! Schema::hasTable('church_files')) {
            return;
        }

        $rows = DB::table('public_material_menu_files')
            ->join('church_files', 'church_files.id', '=', 'public_material_menu_files.church_file_id')
            ->join('public_material_menus', 'public_material_menus.id', '=', 'public_material_menu_files.public_material_menu_id')
            ->select([
                'public_material_menus.menu_key',
                'church_files.public_id',
                'church_files.title',
                'church_files.category_name',
                'church_files.description',
                'church_files.relative_path',
                'church_files.original_file_name',
                'church_files.size_bytes',
                'church_files.mime_type',
                'church_files.created_at',
                'church_files.updated_at',
            ])
            ->orderBy('public_material_menu_files.id')
            ->get();

        foreach ($rows as $row) {
            DB::table('public_material_files')->updateOrInsert(
                ['public_id' => $row->public_id],
                [
                    'menu' => $row->menu_key,
                    'title' => $row->title,
                    'category_name' => $row->category_name,
                    'description' => $row->description,
                    'relative_path' => $row->relative_path,
                    'original_file_name' => $row->original_file_name,
                    'size_bytes' => $row->size_bytes ?? 0,
                    'mime_type' => $row->mime_type,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ],
            );
        }
    }

    private function backfillWorshipSchedules(): void
    {
        if (! Schema::hasTable('worship_schedules') || ! Schema::hasTable('worship_service_schedules')) {
            return;
        }

        $branchIds = DB::table('branches')->pluck('id', 'code')->all();
        foreach (DB::table('worship_service_schedules')->orderBy('month')->get() as $schedule) {
            $branchCode = $this->normalizeBranchCode((string) ($schedule->branch_code ?? 'kutisari'));
            $weeks = Schema::hasTable('worship_service_schedule_weeks')
                ? DB::table('worship_service_schedule_weeks')
                    ->where('worship_service_schedule_id', $schedule->id)
                    ->orderBy('week_index')
                    ->get()
                : collect();
            $weekIdsByIndex = [];
            foreach ($weeks as $week) {
                $weekIdsByIndex[(int) $week->week_index] = (int) $week->id;
            }

            $rows = [];
            if (Schema::hasTable('worship_service_schedule_roles')) {
                foreach (DB::table('worship_service_schedule_roles')->where('worship_service_schedule_id', $schedule->id)->orderBy('sort_order')->orderBy('id')->get() as $role) {
                    $assignments = array_fill(0, max(1, count($weekIdsByIndex)), '');
                    if (Schema::hasTable('worship_service_assignments')) {
                        $assignmentRows = DB::table('worship_service_assignments')
                            ->where('worship_service_schedule_role_id', $role->id)
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->get();

                        $linesByWeek = [];
                        foreach ($assignmentRows as $assignment) {
                            $weekIndex = array_search((int) $assignment->worship_service_schedule_week_id, $weekIdsByIndex, true);
                            if ($weekIndex === false) {
                                continue;
                            }
                            $linesByWeek[(int) $weekIndex][] = (string) $assignment->assignee_name;
                        }
                        foreach ($linesByWeek as $weekIndex => $lines) {
                            $assignments[$weekIndex] = implode("\n", $lines);
                        }
                    }

                    $rows[] = [
                        'role' => (string) $role->role_name,
                        'assignments' => $assignments,
                    ];
                }
            }

            if (count($weekIdsByIndex) > 0) {
                $trainingAssignments = array_fill(0, count($weekIdsByIndex), '');
                foreach ($weeks as $week) {
                    $trainingAssignments[(int) $week->week_index] = (string) ($week->training_date ?? '');
                }
                $rows[] = [
                    'role' => 'Jadwal Latihan',
                    'assignments' => $trainingAssignments,
                ];
            }

            DB::table('worship_schedules')->updateOrInsert(
                ['branch_code' => $branchCode, 'month' => $schedule->month],
                [
                    'title' => $schedule->title,
                    'update_note' => $schedule->update_note,
                    'branch_id' => $branchIds[$branchCode] ?? null,
                    'rows' => json_encode($rows),
                    'created_at' => $schedule->created_at ?? now(),
                    'updated_at' => $schedule->updated_at ?? now(),
                ],
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function branchScopedTables(): array
    {
        return [
            'users',
            'login_attempts',
            'difficult_questions',
            'discipleship_targets',
            'discipleship_people',
            'discipleship_groups',
            'discipleship_relationships',
            'discipleship_group_memberships',
            'discipleship_group_leaderships',
            'discipleship_group_multiplications',
            'msk_participants',
            'discipleship_meeting_reports',
            'discipleship_member_feedback_journals',
            'church_files',
            'worship_service_schedules',
        ];
    }

    private function normalizeBranchCode(string $branchCode): string
    {
        $branchCode = strtolower(trim($branchCode));
        $allowed = ['kutisari', 'gm', 'darmo', 'merr', 'batam', 'nginden'];

        return in_array($branchCode, $allowed, true) ? $branchCode : 'kutisari';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $columns
     */
    private function bulkUpdateById(string $table, array $rows, array $columns): void
    {
        if ($rows === [] || $columns === []) {
            return;
        }

        $assignments = [];
        $bindings = [];
        foreach ($columns as $column) {
            $caseSql = "`{$column}` = CASE `id`";
            foreach ($rows as $row) {
                $caseSql .= ' WHEN ? THEN ?';
                $bindings[] = (int) ($row['id'] ?? 0);
                $bindings[] = $row[$column] ?? null;
            }
            $caseSql .= " ELSE `{$column}` END";
            $assignments[] = $caseSql;
        }

        $ids = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows,
        )));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return;
        }

        $bindings = array_merge($bindings, $ids);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        DB::update(
            "UPDATE `{$table}` SET " . implode(', ', $assignments) . " WHERE `id` IN ({$placeholders})",
            $bindings,
        );
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }
};
