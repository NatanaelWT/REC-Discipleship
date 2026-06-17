<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupLeadership;
use App\Models\DiscipleshipGroupMembership;
use App\Models\DiscipleshipGroupMultiplication;
use App\Models\DiscipleshipMeetingReport;
use App\Models\DiscipleshipMeetingReportAbsence;
use App\Models\DiscipleshipMeetingReportMeditationSharer;
use App\Models\DiscipleshipMeetingReportPhoto;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Models\MskParticipant;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PeopleTreeModelStore
{
    /**
     * @return array<int, string>
     */
    public function branchCodesForSelection(string $selectedBranch, bool $centralReadOnly): array
    {
        if ($centralReadOnly && $selectedBranch === 'all') {
            return array_values(array_filter(array_map(
                static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? '')),
                public_dg_branch_options(),
            ), static fn (string $branchCode): bool => $branchCode !== ''));
        }

        $branchCode = normalize_public_branch_code($selectedBranch);

        return $branchCode !== '' ? [$branchCode] : [];
    }

    /**
     * @return array<string, string>
     */
    public function branchLabels(): array
    {
        $labels = [];
        foreach (public_dg_branch_options() as $option) {
            $branchCode = normalize_public_branch_code((string) ($option['code'] ?? ''));
            if ($branchCode === '') {
                continue;
            }

            $label = trim((string) ($option['label'] ?? strtoupper($branchCode)));
            $labels[$branchCode] = $label !== '' ? $label : strtoupper($branchCode);
        }

        return $labels;
    }

    /**
     * @param array<int, string> $branchCodes
     */
    public function modelForContext(array $branchCodes, bool $centralReadOnly): array
    {
        if (! $centralReadOnly) {
            return $this->modelForBranch((string) ($branchCodes[0] ?? current_user_branch()));
        }

        $combinedModel = dgv2_empty_model();
        foreach ($branchCodes as $branchCode) {
            $branchCode = normalize_public_branch_code($branchCode);
            if ($branchCode === '') {
                continue;
            }

            $this->appendScopedBranchModel($combinedModel, $branchCode, $this->modelForBranch($branchCode));
        }

        return dgv2_normalize_model($combinedModel);
    }

    public function modelForBranch(string $branchCode): array
    {
        $branchCode = normalize_public_branch_code($branchCode);
        if ($branchCode === '') {
            return dgv2_empty_model();
        }

        $model = dgv2_empty_model();
        if (! $this->hasPeopleTreeTables()) {
            return $model;
        }

        $model['discipleship_persons'] = $this->peopleRows($branchCode);
        $model['discipleship_groups'] = $this->groupRows($branchCode);
        $model['discipleship_relations'] = $this->relationshipRows($branchCode);
        $model['group_memberships'] = $this->membershipRows($branchCode);
        $model['group_leaderships'] = $this->leadershipRows($branchCode);
        $model['group_multiplications'] = $this->multiplicationRows($branchCode);

        return dgv2_normalize_model($model);
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    public function participantsForBranches(array $branchCodes, bool $centralReadOnly): array
    {
        $branchCodes = $this->normalizeBranchCodes($branchCodes);
        if ($branchCodes === []) {
            return [];
        }

        if (! Schema::hasTable('msk_participants')) {
            return [];
        }

        $branchLabels = $this->branchLabels();
        $rows = [];

        MskParticipant::query()
            ->with([
                'sessions' => static fn ($query) => $query->orderBy('session_number'),
                'photos' => static fn ($query) => $query->orderBy('id'),
            ])
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('full_name')
            ->orderBy('id')
            ->get()
            ->each(function (MskParticipant $participant) use (&$rows, $centralReadOnly, $branchLabels): void {
                $branchCode = normalize_public_branch_code((string) $participant->branch_code);
                $branchLabel = $branchLabels[$branchCode] ?? strtoupper($branchCode);
                $row = $participant->toViewArray();
                $row['branch_code'] = $branchCode;
                $row['branch_label'] = $branchLabel;

                if ($centralReadOnly) {
                    $row['id'] = scoped_virtual_id($branchCode, (string) ($row['id'] ?? ''));
                    $memberId = trim((string) ($row['member_id'] ?? ''));
                    $row['member_id'] = $memberId !== '' ? scoped_virtual_id($branchCode, $memberId) : '';
                    $row['full_name'] = append_branch_suffix((string) ($row['full_name'] ?? ''), $branchLabel);
                }

                $rows[] = $row;
            });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function peopleForModel(array $model, array $members, array $mskClasses, bool $centralReadOnly): array
    {
        $people = dgv2_people_projection($model, $members, $mskClasses);
        $branchLabels = $this->branchLabels();
        $personBranches = [];

        foreach (($model['discipleship_persons'] ?? []) as $personRow) {
            if (! is_array($personRow)) {
                continue;
            }

            $personId = trim((string) ($personRow['id'] ?? ''));
            if ($personId === '') {
                continue;
            }
            $branchCode = normalize_public_branch_code((string) ($personRow['branch_code'] ?? ''));
            $personBranches[$personId] = [
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
            ];
        }

        foreach ($people as &$personRow) {
            $personId = trim((string) ($personRow['id'] ?? ''));
            $branchCode = normalize_public_branch_code((string) ($personBranches[$personId]['branch_code'] ?? current_user_branch()));
            $branchLabel = (string) ($personBranches[$personId]['branch_label'] ?? ($branchLabels[$branchCode] ?? strtoupper($branchCode)));
            $personRow['branch_code'] = $branchCode;
            $personRow['branch_label'] = $branchLabel;
            if ($centralReadOnly) {
                $personRow['name'] = append_branch_suffix((string) ($personRow['name'] ?? ''), $branchLabel);
            }
        }
        unset($personRow);

        return $people;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupsForModel(array $model, array $people, bool $centralReadOnly): array
    {
        $groups = dgv2_groups_projection($model, index_by_id($people));
        if (! $centralReadOnly) {
            return $groups;
        }

        $branchLabels = $this->branchLabels();
        $groupBranches = [];
        foreach (($model['discipleship_groups'] ?? []) as $groupRow) {
            if (! is_array($groupRow)) {
                continue;
            }
            $groupId = trim((string) ($groupRow['id'] ?? ''));
            if ($groupId === '') {
                continue;
            }
            $branchCode = normalize_public_branch_code((string) ($groupRow['branch_code'] ?? ''));
            $groupBranches[$groupId] = $branchLabels[$branchCode] ?? strtoupper($branchCode);
        }

        foreach ($groups as &$groupRow) {
            $groupId = trim((string) ($groupRow['id'] ?? ''));
            $branchLabel = (string) ($groupBranches[$groupId] ?? '');
            if ($branchLabel !== '') {
                $groupRow['name'] = '[' . $branchLabel . '] ' . trim((string) ($groupRow['name'] ?? 'Kelompok'));
                $groupRow['leader_name'] = append_branch_suffix((string) ($groupRow['leader_name'] ?? ''), $branchLabel);
            }
        }
        unset($groupRow);

        return $groups;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    public function meetingReportsForBranches(array $branchCodes, bool $centralReadOnly): array
    {
        $branchCodes = $this->normalizeBranchCodes($branchCodes);
        if ($branchCodes === [] || ! Schema::hasTable('discipleship_meeting_reports')) {
            return [];
        }

        $branchLabels = $this->branchLabels();
        $rows = [];

        DiscipleshipMeetingReport::query()
            ->with(['absences', 'meditationSharers', 'photos'])
            ->whereIn('branch_code', $branchCodes)
            ->orderByDesc('meeting_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->each(function (DiscipleshipMeetingReport $report) use (&$rows, $branchLabels, $centralReadOnly): void {
                $branchCode = normalize_public_branch_code((string) $report->branch_code);
                $branchLabel = $branchLabels[$branchCode] ?? strtoupper($branchCode);
                $leaderId = $this->contextPublicId($branchCode, (string) ($report->leader_person_public_id ?? ''), $centralReadOnly);
                $groupId = $this->contextPublicId($branchCode, (string) ($report->discipleship_group_public_id ?? ''), $centralReadOnly);
                $leaderName = trim((string) ($report->leader_name_snapshot ?? ''));
                $groupName = trim((string) ($report->group_name_snapshot ?? 'Kelompok')) ?: 'Kelompok';

                if ($centralReadOnly) {
                    if ($leaderName !== '') {
                        $leaderName = append_branch_suffix($leaderName, $branchLabel);
                    }
                    $groupName = append_branch_suffix($groupName, $branchLabel);
                }

                $rows[] = [
                    'id' => $this->contextPublicId($branchCode, (string) $report->public_id, $centralReadOnly),
                    'branch_code' => $branchCode,
                    'branch_label' => $branchLabel,
                    'leader_id' => $leaderId,
                    'leader_name' => $leaderName,
                    'group_id' => $groupId,
                    'group_name' => $groupName,
                    'meeting_date' => $this->dateString($report->meeting_date ?? null),
                    'material_topic' => trim((string) ($report->material_topic ?? '')),
                    'group_progress' => normalize_dg_progress_value((string) ($report->group_progress_snapshot ?? '')) ?: 'DG 1',
                    'absence_reason' => trim((string) ($report->absence_reason ?? '')),
                    'absent_member_ids' => $this->reportPersonIds($branchCode, $report->absences, $centralReadOnly),
                    'additional_notes' => trim((string) ($report->additional_notes ?? '')),
                    'meditation_min_times' => max(0, (int) $report->meditation_min_times),
                    'meditation_sharer_ids' => $this->reportPersonIds($branchCode, $report->meditationSharers, $centralReadOnly),
                    'meeting_photos' => $this->reportPhotos($report->photos),
                    'quality_pray' => $report->prayed_for_members ? 'true' : 'false',
                    'quality_prepare' => $report->prepared_material ? 'true' : 'false',
                    'quality_relational' => $report->relationally_contacted ? 'true' : 'false',
                    'quality_share_meditation' => $report->shared_meditation ? 'true' : 'false',
                    'sharing_openness' => $report->sharing_openness_score,
                    'source' => trim((string) ($report->source ?? 'public_form')) ?: 'public_form',
                    'created_at' => $this->timestampString($report->created_at ?? null),
                    'updated_at' => $this->timestampString($report->updated_at ?? null),
                ];
            });

        return $rows;
    }

    public function replaceBranchModel(string $branchCode, array $model): void
    {
        $branchCode = normalize_public_branch_code($branchCode);
        if ($branchCode === '' || ! $this->hasPeopleTreeTables()) {
            return;
        }

        DB::transaction(function () use ($branchCode, $model): void {
            $personPublicIds = $this->migratePeople($branchCode, $model['discipleship_persons'] ?? []);
            $groupPublicIds = $this->migrateGroups($branchCode, $model['discipleship_groups'] ?? []);

            $this->removeMissingRows('discipleship_people', $branchCode, $personPublicIds);
            $this->removeMissingRows('discipleship_groups', $branchCode, $groupPublicIds);
            $this->syncGroupParents($branchCode);

            $personIds = $this->idsByPublicId('discipleship_people', $branchCode);
            $groupIds = $this->idsByPublicId('discipleship_groups', $branchCode);

            $this->clearBranchRelations($branchCode);
            $this->insertRelationships($branchCode, $model['discipleship_relations'] ?? [], $personIds, $groupIds);
            $this->insertMemberships($branchCode, $model['group_memberships'] ?? [], $personIds, $groupIds);
            $this->insertLeaderships($branchCode, $model['group_leaderships'] ?? [], $personIds, $groupIds);
            $this->insertMultiplications($branchCode, $model['group_multiplications'] ?? [], $personIds, $groupIds);
        });
    }

    private function hasPeopleTreeTables(): bool
    {
        return Schema::hasTable('discipleship_people')
            && Schema::hasTable('discipleship_groups')
            && Schema::hasTable('discipleship_relationships')
            && Schema::hasTable('discipleship_group_memberships')
            && Schema::hasTable('discipleship_group_leaderships')
            && Schema::hasTable('discipleship_group_multiplications');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peopleRows(string $branchCode): array
    {
        return DiscipleshipPerson::query()
            ->where('branch_code', $branchCode)
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipPerson $person): array => [
                'id' => (string) $person->public_id,
                'member_id' => trim((string) ($person->member_public_id ?? '')),
                'full_name' => trim((string) ($person->full_name ?? '')),
                'phone' => trim((string) ($person->phone ?? '')),
                'gender' => trim((string) ($person->gender ?? '')),
                'status' => trim((string) ($person->status ?? 'active')) ?: 'active',
                'notes' => trim((string) ($person->notes ?? '')),
                'kampus' => trim((string) ($person->campus ?? '')),
                'jurusan' => trim((string) ($person->major ?? '')),
                'pekerjaan' => trim((string) ($person->occupation ?? '')),
                'branch_code' => $branchCode,
                'created_at' => $this->timestampString($person->created_at ?? null),
                'updated_at' => $this->timestampString($person->updated_at ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(string $branchCode): array
    {
        return DiscipleshipGroup::query()
            ->where('branch_code', $branchCode)
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipGroup $group): array => [
                'id' => (string) $group->public_id,
                'name' => trim((string) ($group->name ?? 'Kelompok')) ?: 'Kelompok',
                'status' => trim((string) ($group->status ?? 'active')) ?: 'active',
                'start_stage' => normalize_dg_progress_value((string) ($group->start_stage ?? '')),
                'current_stage' => normalize_dg_progress_value((string) ($group->current_stage ?? '')),
                'parent_group_id' => trim((string) ($group->parent_group_public_id ?? '')),
                'notes' => trim((string) ($group->notes ?? '')),
                'branch_code' => $branchCode,
                'created_at' => $this->timestampString($group->created_at ?? null),
                'updated_at' => $this->timestampString($group->updated_at ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relationshipRows(string $branchCode): array
    {
        return DiscipleshipRelationship::query()
            ->where('branch_code', $branchCode)
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipRelationship $relationship): array => [
                'id' => trim((string) ($relationship->public_id ?? 'rel_db_' . (string) $relationship->id)),
                'mentor_person_id' => trim((string) ($relationship->mentor_person_public_id ?? '')),
                'disciple_person_id' => trim((string) ($relationship->disciple_person_public_id ?? '')),
                'context_group_id' => trim((string) ($relationship->context_group_public_id ?? '')),
                'relation_type' => trim((string) ($relationship->relation_type ?? '')),
                'stage_at_start' => normalize_dg_progress_value((string) ($relationship->stage_at_start ?? '')),
                'status' => trim((string) ($relationship->status ?? 'active')) ?: 'active',
                'start_date' => $this->dateString($relationship->start_date ?? null),
                'end_date' => $this->dateString($relationship->end_date ?? null),
                'reason_end' => trim((string) ($relationship->reason_end ?? '')),
                'notes' => trim((string) ($relationship->notes ?? '')),
                'branch_code' => $branchCode,
                'created_at' => $this->timestampString($relationship->created_at ?? null),
                'updated_at' => $this->timestampString($relationship->updated_at ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function membershipRows(string $branchCode): array
    {
        return DiscipleshipGroupMembership::query()
            ->where('branch_code', $branchCode)
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipGroupMembership $membership): array => [
                'id' => trim((string) ($membership->public_id ?? 'mem_db_' . (string) $membership->id)),
                'group_id' => trim((string) ($membership->group_public_id ?? '')),
                'person_id' => trim((string) ($membership->person_public_id ?? '')),
                'role' => trim((string) ($membership->role ?? 'member')) ?: 'member',
                'stage' => normalize_dg_progress_value((string) ($membership->stage ?? '')),
                'status' => trim((string) ($membership->status ?? 'active')) ?: 'active',
                'start_date' => $this->dateString($membership->start_date ?? null),
                'end_date' => $this->dateString($membership->end_date ?? null),
                'reason_end' => trim((string) ($membership->reason_end ?? '')),
                'branch_code' => $branchCode,
                'created_at' => $this->timestampString($membership->created_at ?? null),
                'updated_at' => $this->timestampString($membership->updated_at ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function leadershipRows(string $branchCode): array
    {
        return DiscipleshipGroupLeadership::query()
            ->where('branch_code', $branchCode)
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipGroupLeadership $leadership): array => [
                'id' => trim((string) ($leadership->public_id ?? 'ldr_db_' . (string) $leadership->id)),
                'group_id' => trim((string) ($leadership->group_public_id ?? '')),
                'leader_person_id' => trim((string) ($leadership->person_public_id ?? '')),
                'role' => trim((string) ($leadership->role ?? 'leader')) ?: 'leader',
                'status' => trim((string) ($leadership->status ?? 'active')) ?: 'active',
                'start_date' => $this->dateString($leadership->start_date ?? null),
                'end_date' => $this->dateString($leadership->end_date ?? null),
                'reason_change' => trim((string) ($leadership->reason_change ?? '')),
                'branch_code' => $branchCode,
                'created_at' => $this->timestampString($leadership->created_at ?? null),
                'updated_at' => $this->timestampString($leadership->updated_at ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function multiplicationRows(string $branchCode): array
    {
        return DiscipleshipGroupMultiplication::query()
            ->where('branch_code', $branchCode)
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipGroupMultiplication $multiplication): array => [
                'id' => trim((string) ($multiplication->public_id ?? 'gmx_db_' . (string) $multiplication->id)),
                'initiated_by_person_id' => trim((string) ($multiplication->initiated_by_person_public_id ?? '')),
                'source_group_id' => trim((string) ($multiplication->source_group_public_id ?? '')),
                'new_group_id' => trim((string) ($multiplication->new_group_public_id ?? '')),
                'start_date' => $this->dateString($multiplication->multiplication_date ?? null),
                'notes' => trim((string) ($multiplication->notes ?? '')),
                'branch_code' => $branchCode,
                'created_at' => $this->timestampString($multiplication->created_at ?? null),
                'updated_at' => $this->timestampString($multiplication->updated_at ?? null),
            ])
            ->values()
            ->all();
    }

    private function appendScopedBranchModel(array &$combinedModel, string $branchCode, array $branchModel): void
    {
        foreach (($branchModel['discipleship_persons'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out = $row;
            $out['id'] = scoped_virtual_id($branchCode, (string) ($row['id'] ?? ''));
            $memberId = trim((string) ($row['member_id'] ?? ''));
            $out['member_id'] = $memberId !== '' ? scoped_virtual_id($branchCode, $memberId) : '';
            $out['branch_code'] = $branchCode;
            $combinedModel['discipleship_persons'][] = $out;
        }

        foreach (($branchModel['discipleship_groups'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out = $row;
            $out['id'] = scoped_virtual_id($branchCode, (string) ($row['id'] ?? ''));
            $parentGroupId = trim((string) ($row['parent_group_id'] ?? ''));
            $out['parent_group_id'] = $parentGroupId !== '' ? scoped_virtual_id($branchCode, $parentGroupId) : '';
            $out['branch_code'] = $branchCode;
            $combinedModel['discipleship_groups'][] = $out;
        }

        foreach (($branchModel['group_memberships'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out = $row;
            $out['id'] = scoped_virtual_id($branchCode, (string) ($row['id'] ?? ''));
            $out['group_id'] = scoped_virtual_id($branchCode, (string) ($row['group_id'] ?? ''));
            $out['person_id'] = scoped_virtual_id($branchCode, (string) ($row['person_id'] ?? ''));
            $out['branch_code'] = $branchCode;
            $combinedModel['group_memberships'][] = $out;
        }

        foreach (($branchModel['group_leaderships'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out = $row;
            $out['id'] = scoped_virtual_id($branchCode, (string) ($row['id'] ?? ''));
            $out['group_id'] = scoped_virtual_id($branchCode, (string) ($row['group_id'] ?? ''));
            $out['leader_person_id'] = scoped_virtual_id($branchCode, (string) ($row['leader_person_id'] ?? ''));
            $out['branch_code'] = $branchCode;
            $combinedModel['group_leaderships'][] = $out;
        }

        foreach (($branchModel['discipleship_relations'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out = $row;
            $out['id'] = scoped_virtual_id($branchCode, (string) ($row['id'] ?? ''));
            $mentorId = trim((string) ($row['mentor_person_id'] ?? ''));
            $out['mentor_person_id'] = $mentorId !== '' && $mentorId !== 'virtual_injil' ? scoped_virtual_id($branchCode, $mentorId) : $mentorId;
            $out['disciple_person_id'] = scoped_virtual_id($branchCode, (string) ($row['disciple_person_id'] ?? ''));
            $contextGroupId = trim((string) ($row['context_group_id'] ?? ''));
            $out['context_group_id'] = $contextGroupId !== '' ? scoped_virtual_id($branchCode, $contextGroupId) : '';
            $out['branch_code'] = $branchCode;
            $combinedModel['discipleship_relations'][] = $out;
        }

        foreach (($branchModel['group_multiplications'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out = $row;
            $out['id'] = scoped_virtual_id($branchCode, (string) ($row['id'] ?? ''));
            $out['initiated_by_person_id'] = scoped_virtual_id($branchCode, (string) ($row['initiated_by_person_id'] ?? ''));
            $out['source_group_id'] = scoped_virtual_id($branchCode, (string) ($row['source_group_id'] ?? ''));
            $out['new_group_id'] = scoped_virtual_id($branchCode, (string) ($row['new_group_id'] ?? ''));
            $out['branch_code'] = $branchCode;
            $combinedModel['group_multiplications'][] = $out;
        }
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, string>
     */
    private function normalizeBranchCodes(array $branchCodes): array
    {
        $normalized = [];
        foreach ($branchCodes as $branchCode) {
            $branchCode = normalize_public_branch_code((string) $branchCode);
            if ($branchCode !== '') {
                $normalized[] = $branchCode;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function contextPublicId(string $branchCode, string $publicId, bool $centralReadOnly): string
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return '';
        }

        return $centralReadOnly ? scoped_virtual_id($branchCode, $publicId) : $publicId;
    }

    /**
     * @param iterable<int, DiscipleshipMeetingReportAbsence|DiscipleshipMeetingReportMeditationSharer> $rows
     * @return array<int, string>
     */
    private function reportPersonIds(string $branchCode, iterable $rows, bool $centralReadOnly): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $personId = $this->contextPublicId($branchCode, (string) ($row->person_public_id ?? ''), $centralReadOnly);
            if ($personId !== '' && ! in_array($personId, $ids, true)) {
                $ids[] = $personId;
            }
        }

        return $ids;
    }

    /**
     * @param iterable<int, DiscipleshipMeetingReportPhoto> $photos
     * @return array<int, array{path: string, name: string}>
     */
    private function reportPhotos(iterable $photos): array
    {
        $rows = [];
        foreach ($photos as $photo) {
            $path = sanitize_relative_upload_path((string) ($photo->relative_path ?? ''));
            if ($path === '') {
                continue;
            }
            $rows[] = [
                'path' => $path,
                'name' => trim((string) ($photo->original_file_name ?? '')) ?: basename($path),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, string>
     */
    private function migratePeople(string $branchCode, array $rows): array
    {
        $publicIds = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $publicId = $this->publicId($row);
            if ($publicId === null) {
                continue;
            }

            $createdAt = $this->timestampFrom([$row['created_at'] ?? null]);
            $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $createdAt]);

            DB::table('discipleship_people')->updateOrInsert(
                ['branch_code' => $branchCode, 'public_id' => $publicId],
                [
                    'member_public_id' => $this->nullableString($row['member_id'] ?? null),
                    'full_name' => $this->nullableString($row['full_name'] ?? $row['name'] ?? null),
                    'phone' => $this->nullableString($row['phone'] ?? $row['whatsapp'] ?? null),
                    'gender' => $this->nullableString($row['gender'] ?? null),
                    'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                    'notes' => $this->nullableString($row['notes'] ?? null),
                    'campus' => $this->nullableString($row['kampus'] ?? $row['campus'] ?? null),
                    'major' => $this->nullableString($row['jurusan'] ?? $row['major'] ?? null),
                    'occupation' => $this->nullableString($row['pekerjaan'] ?? $row['occupation'] ?? null),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ],
            );

            $publicIds[] = $publicId;
        }

        return array_values(array_unique($publicIds));
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, string>
     */
    private function migrateGroups(string $branchCode, array $rows): array
    {
        $publicIds = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $publicId = $this->publicId($row);
            if ($publicId === null) {
                continue;
            }

            $createdAt = $this->timestampFrom([$row['created_at'] ?? null]);
            $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $createdAt]);

            DB::table('discipleship_groups')->updateOrInsert(
                ['branch_code' => $branchCode, 'public_id' => $publicId],
                [
                    'name' => $this->nullableString($row['name'] ?? null) ?? 'Kelompok',
                    'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                    'start_stage' => $this->normalizedProgress($row['start_stage'] ?? null),
                    'current_stage' => $this->normalizedProgress($row['current_stage'] ?? $row['start_stage'] ?? null),
                    'parent_group_id' => null,
                    'parent_group_public_id' => $this->nullableString($row['parent_group_id'] ?? null),
                    'notes' => $this->nullableString($row['notes'] ?? null),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ],
            );

            $publicIds[] = $publicId;
        }

        return array_values(array_unique($publicIds));
    }

    /**
     * @param array<int, string> $publicIds
     */
    private function removeMissingRows(string $table, string $branchCode, array $publicIds): void
    {
        $query = DB::table($table)->where('branch_code', $branchCode);
        if ($publicIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('public_id', $publicIds)->delete();
    }

    private function syncGroupParents(string $branchCode): void
    {
        $groupIds = $this->idsByPublicId('discipleship_groups', $branchCode);
        $groups = DB::table('discipleship_groups')
            ->where('branch_code', $branchCode)
            ->select(['id', 'parent_group_public_id'])
            ->get();

        foreach ($groups as $group) {
            $parentPublicId = $this->nullableString($group->parent_group_public_id ?? null);
            $parentId = $parentPublicId !== null && isset($groupIds[$parentPublicId]) ? $groupIds[$parentPublicId] : null;

            DB::table('discipleship_groups')
                ->where('id', (int) $group->id)
                ->update(['parent_group_id' => $parentId]);
        }
    }

    private function clearBranchRelations(string $branchCode): void
    {
        DB::table('discipleship_group_multiplications')->where('branch_code', $branchCode)->delete();
        DB::table('discipleship_group_leaderships')->where('branch_code', $branchCode)->delete();
        DB::table('discipleship_group_memberships')->where('branch_code', $branchCode)->delete();
        DB::table('discipleship_relationships')->where('branch_code', $branchCode)->delete();
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<string, int> $personIds
     * @param array<string, int> $groupIds
     */
    private function insertRelationships(string $branchCode, array $rows, array $personIds, array $groupIds): void
    {
        $inserts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $publicId = $this->publicId($row);
            if ($publicId === null) {
                continue;
            }

            $mentorPublicId = $this->nullableString($row['mentor_person_id'] ?? null);
            $disciplePublicId = $this->nullableString($row['disciple_person_id'] ?? null);
            $contextGroupPublicId = $this->nullableString($row['context_group_id'] ?? null);
            $createdAt = $this->timestampFrom([$row['created_at'] ?? null]);
            $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $createdAt]);

            $inserts[] = [
                'public_id' => $publicId,
                'branch_code' => $branchCode,
                'mentor_person_id' => $this->mappedId($personIds, $mentorPublicId),
                'mentor_person_public_id' => $mentorPublicId,
                'disciple_person_id' => $this->mappedId($personIds, $disciplePublicId),
                'disciple_person_public_id' => $disciplePublicId,
                'context_group_id' => $this->mappedId($groupIds, $contextGroupPublicId),
                'context_group_public_id' => $contextGroupPublicId,
                'relation_type' => $this->nullableString($row['relation_type'] ?? null),
                'stage_at_start' => $this->normalizedProgress($row['stage_at_start'] ?? $row['stage'] ?? null),
                'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                'start_date' => $this->dateValue($row['start_date'] ?? null),
                'end_date' => $this->dateValue($row['end_date'] ?? null),
                'reason_end' => $this->nullableString($row['reason_end'] ?? $row['reason_close'] ?? null),
                'notes' => $this->nullableString($row['notes'] ?? null),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        $this->insertChunked('discipleship_relationships', $inserts);
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<string, int> $personIds
     * @param array<string, int> $groupIds
     */
    private function insertMemberships(string $branchCode, array $rows, array $personIds, array $groupIds): void
    {
        $inserts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $publicId = $this->publicId($row);
            $groupPublicId = $this->nullableString($row['group_id'] ?? null);
            if ($publicId === null || $groupPublicId === null || ! isset($groupIds[$groupPublicId])) {
                continue;
            }

            $personPublicId = $this->nullableString($row['person_id'] ?? null);
            $createdAt = $this->timestampFrom([$row['created_at'] ?? null]);
            $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $createdAt]);
            $inserts[] = [
                'public_id' => $publicId,
                'branch_code' => $branchCode,
                'discipleship_group_id' => $groupIds[$groupPublicId],
                'group_public_id' => $groupPublicId,
                'person_id' => $this->mappedId($personIds, $personPublicId),
                'person_public_id' => $personPublicId,
                'role' => $this->nullableString($row['role'] ?? null) ?? 'member',
                'stage' => $this->normalizedProgress($row['stage'] ?? null),
                'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                'start_date' => $this->dateValue($row['start_date'] ?? null),
                'end_date' => $this->dateValue($row['end_date'] ?? null),
                'reason_end' => $this->nullableString($row['reason_end'] ?? null),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        $this->insertChunked('discipleship_group_memberships', $inserts);
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<string, int> $personIds
     * @param array<string, int> $groupIds
     */
    private function insertLeaderships(string $branchCode, array $rows, array $personIds, array $groupIds): void
    {
        $inserts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $publicId = $this->publicId($row);
            $groupPublicId = $this->nullableString($row['group_id'] ?? null);
            if ($publicId === null || $groupPublicId === null || ! isset($groupIds[$groupPublicId])) {
                continue;
            }

            $personPublicId = $this->nullableString($row['leader_person_id'] ?? $row['person_id'] ?? null);
            $createdAt = $this->timestampFrom([$row['created_at'] ?? null]);
            $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $createdAt]);
            $inserts[] = [
                'public_id' => $publicId,
                'branch_code' => $branchCode,
                'discipleship_group_id' => $groupIds[$groupPublicId],
                'group_public_id' => $groupPublicId,
                'person_id' => $this->mappedId($personIds, $personPublicId),
                'person_public_id' => $personPublicId,
                'role' => $this->nullableString($row['role'] ?? null) ?? 'leader',
                'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                'start_date' => $this->dateValue($row['start_date'] ?? null),
                'end_date' => $this->dateValue($row['end_date'] ?? null),
                'reason_change' => $this->nullableString($row['reason_change'] ?? null),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        $this->insertChunked('discipleship_group_leaderships', $inserts);
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<string, int> $personIds
     * @param array<string, int> $groupIds
     */
    private function insertMultiplications(string $branchCode, array $rows, array $personIds, array $groupIds): void
    {
        $inserts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $publicId = $this->publicId($row);
            if ($publicId === null) {
                continue;
            }

            $initiatorPublicId = $this->nullableString($row['initiated_by_person_id'] ?? null);
            $sourceGroupPublicId = $this->nullableString($row['source_group_id'] ?? null);
            $newGroupPublicId = $this->nullableString($row['new_group_id'] ?? null);
            $createdAt = $this->timestampFrom([$row['created_at'] ?? null, $row['start_date'] ?? null]);
            $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $createdAt]);

            $inserts[] = [
                'public_id' => $publicId,
                'branch_code' => $branchCode,
                'initiated_by_person_id' => $this->mappedId($personIds, $initiatorPublicId),
                'initiated_by_person_public_id' => $initiatorPublicId,
                'source_group_id' => $this->mappedId($groupIds, $sourceGroupPublicId),
                'source_group_public_id' => $sourceGroupPublicId,
                'new_group_id' => $this->mappedId($groupIds, $newGroupPublicId),
                'new_group_public_id' => $newGroupPublicId,
                'multiplication_date' => $this->dateValue($row['multiplication_date'] ?? $row['start_date'] ?? null),
                'notes' => $this->nullableString($row['notes'] ?? null),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        $this->insertChunked('discipleship_group_multiplications', $inserts);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            if ($chunk !== []) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function idsByPublicId(string $table, string $branchCode): array
    {
        $ids = [];
        foreach (DB::table($table)->where('branch_code', $branchCode)->select(['id', 'public_id'])->get() as $row) {
            $publicId = $this->nullableString($row->public_id ?? null);
            if ($publicId !== null) {
                $ids[$publicId] = (int) $row->id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, int> $map
     */
    private function mappedId(array $map, ?string $publicId): ?int
    {
        return $publicId === null || ! isset($map[$publicId]) ? null : $map[$publicId];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function publicId(array $row): ?string
    {
        return $this->nullableString($row['id'] ?? $row['public_id'] ?? null);
    }

    private function dateValue(mixed $value): ?string
    {
        $date = normalize_ymd_date((string) $value);

        return $date === '' ? null : $date;
    }

    private function normalizedProgress(mixed $value): ?string
    {
        $progress = normalize_dg_progress_value((string) $value);

        return $progress === '' ? null : $progress;
    }

    /**
     * @param array<int, mixed> $candidates
     */
    private function timestampFrom(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate instanceof CarbonImmutable) {
                return $candidate->format('Y-m-d H:i:s');
            }

            if ($candidate instanceof DateTimeInterface) {
                return $candidate->format('Y-m-d H:i:s');
            }

            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
            } catch (Throwable) {
                continue;
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function timestampString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return trim((string) $value);
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }
}
