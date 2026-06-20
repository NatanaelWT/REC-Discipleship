<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipMeetingReport;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Models\MskParticipant;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class PeopleTreeModelStore
{
    /** @return array<int, string> */
    public function branchCodesForSelection(string $selectedBranch, bool $centralReadOnly): array
    {
        if ($centralReadOnly && $selectedBranch === 'all') {
            return array_values(array_filter(array_map(
                static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? '')),
                public_dg_branch_options(),
            )));
        }

        $branchCode = normalize_public_branch_code($selectedBranch);

        return $branchCode !== '' ? [$branchCode] : [];
    }

    /** @return array<string, string> */
    public function branchLabels(): array
    {
        $labels = [];
        foreach (public_dg_branch_options() as $option) {
            $branchCode = normalize_public_branch_code((string) ($option['code'] ?? ''));
            if ($branchCode !== '') {
                $labels[$branchCode] = trim((string) ($option['label'] ?? '')) ?: strtoupper($branchCode);
            }
        }

        return $labels;
    }

    /** @param array<int, string> $branchCodes */
    public function modelForContext(array $branchCodes, bool $centralReadOnly): array
    {
        $branchIds = branch_ids_from_slugs($this->normalizeBranchCodes($branchCodes));
        if ($branchIds === []) {
            return dgv2_empty_model();
        }

        $groups = $this->groupRows($branchIds);
        $groupPeople = $this->groupPeopleRows($branchIds);
        $memberships = [];
        $leaderships = [];
        foreach ($groupPeople as $row) {
            if (($row['role'] ?? '') === 'member') {
                $memberships[] = $row;
            } else {
                $leaderships[] = $row;
            }
        }

        return dgv2_normalize_model([
            'discipleship_persons' => $this->peopleRows($branchIds),
            'discipleship_groups' => $groups,
            'discipleship_relations' => $this->relationshipRows($branchIds),
            'group_memberships' => $memberships,
            'group_leaderships' => $leaderships,
            'group_multiplications' => $this->multiplicationRows($groups),
        ]);
    }

    public function modelForBranch(string $branchCode): array
    {
        $branchCode = normalize_public_branch_code($branchCode);
        if ($branchCode === '') {
            return dgv2_empty_model();
        }

        return $this->modelForContext([$branchCode], false);
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    public function participantsForBranches(array $branchCodes, bool $centralReadOnly): array
    {
        $branchCodes = $this->normalizeBranchCodes($branchCodes);
        if ($branchCodes === []) {
            return [];
        }

        $labels = $this->branchLabels();

        try {
            return MskParticipant::query()
                ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
                ->orderBy('full_name')
                ->orderBy('id')
                ->get()
                ->map(static function (MskParticipant $participant) use ($centralReadOnly, $labels): array {
                    $row = $participant->toViewArray();
                    $branchCode = normalize_public_branch_code((string) $participant->branch_code);
                    $branchLabel = $labels[$branchCode] ?? strtoupper($branchCode);
                    $row['branch_code'] = $branchCode;
                    $row['branch_label'] = $branchLabel;
                    if ($centralReadOnly) {
                        $row['full_name'] = append_branch_suffix((string) ($row['full_name'] ?? ''), $branchLabel);
                    }

                    return $row;
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function peopleForModel(array $model, array $members, array $mskClasses, bool $centralReadOnly): array
    {
        $people = dgv2_people_projection($model, $members, $mskClasses);
        $labels = $this->branchLabels();
        $branches = [];
        foreach ($model['discipleship_persons'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $branchCode = normalize_public_branch_code((string) ($row['branch_code'] ?? ''));
            if ($id !== '') {
                $branches[$id] = [$branchCode, $labels[$branchCode] ?? strtoupper($branchCode)];
            }
        }

        foreach ($people as &$row) {
            $id = trim((string) ($row['id'] ?? ''));
            [$branchCode, $branchLabel] = $branches[$id] ?? [normalize_public_branch_code(current_user_branch()), ''];
            $row['branch_code'] = $branchCode;
            $row['branch_label'] = $branchLabel;
            if ($centralReadOnly && $branchLabel !== '') {
                $row['name'] = append_branch_suffix((string) ($row['name'] ?? ''), $branchLabel);
            }
        }
        unset($row);

        return $people;
    }

    /** @return array<int, array<string, mixed>> */
    public function groupsForModel(array $model, array $people, bool $centralReadOnly): array
    {
        $groups = dgv2_groups_projection($model, index_by_id($people));
        if (! $centralReadOnly) {
            return $groups;
        }

        $labels = $this->branchLabels();
        $groupLabels = [];
        foreach ($model['discipleship_groups'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $branchCode = normalize_public_branch_code((string) ($row['branch_code'] ?? ''));
            if ($id !== '') {
                $groupLabels[$id] = $labels[$branchCode] ?? strtoupper($branchCode);
            }
        }

        foreach ($groups as &$row) {
            $branchLabel = $groupLabels[(string) ($row['id'] ?? '')] ?? '';
            if ($branchLabel !== '') {
                $row['name'] = '['.$branchLabel.'] '.trim((string) ($row['name'] ?? 'Kelompok'));
                $row['leader_name'] = append_branch_suffix((string) ($row['leader_name'] ?? ''), $branchLabel);
            }
        }
        unset($row);

        return $groups;
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    public function meetingReportsForBranches(array $branchCodes, bool $centralReadOnly): array
    {
        $branchCodes = $this->normalizeBranchCodes($branchCodes);
        if ($branchCodes === []) {
            return [];
        }

        $labels = $this->branchLabels();

        try {
            return DiscipleshipMeetingReport::query()
                ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
                ->orderByDesc('meeting_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(function (DiscipleshipMeetingReport $report) use ($centralReadOnly, $labels): array {
                    $branchCode = normalize_public_branch_code((string) $report->branch_code);
                    $branchLabel = $labels[$branchCode] ?? strtoupper($branchCode);
                    $leaderName = trim((string) ($report->leader_name_snapshot ?? ''));
                    $groupName = trim((string) ($report->group_name_snapshot ?? 'Kelompok')) ?: 'Kelompok';
                    if ($centralReadOnly) {
                        $leaderName = $leaderName !== '' ? append_branch_suffix($leaderName, $branchLabel) : '';
                        $groupName = append_branch_suffix($groupName, $branchLabel);
                    }

                    return [
                        'id' => (string) $report->getKey(),
                        'branch_code' => $branchCode,
                        'branch_label' => $branchLabel,
                        'leader_id' => $report->leader_person_id !== null ? (string) $report->leader_person_id : '',
                        'leader_name' => $leaderName,
                        'group_id' => $report->discipleship_group_id !== null ? (string) $report->discipleship_group_id : '',
                        'group_name' => $groupName,
                        'meeting_date' => $this->dateString($report->meeting_date),
                        'material_topic' => trim((string) $report->material_topic),
                        'group_progress' => normalize_dg_progress_value((string) $report->group_progress_snapshot) ?: 'DG 1',
                        'absence_reason' => trim((string) $report->absence_reason),
                        'absent_member_ids' => $this->reportPersonIds($report->absenceItems()),
                        'additional_notes' => trim((string) $report->additional_notes),
                        'meditation_min_times' => max(0, (int) $report->meditation_min_times),
                        'meditation_sharer_ids' => $this->reportPersonIds($report->meditationSharerItems()),
                        'meeting_photos' => $this->reportPhotos($report->photoItems()),
                        'quality_pray' => $report->prayed_for_members ? 'true' : 'false',
                        'quality_prepare' => $report->prepared_material ? 'true' : 'false',
                        'quality_relational' => $report->relationally_contacted ? 'true' : 'false',
                        'quality_share_meditation' => $report->shared_meditation ? 'true' : 'false',
                        'sharing_openness' => $report->sharing_openness_score,
                        'source' => trim((string) ($report->source ?? 'public_form')) ?: 'public_form',
                        'created_at' => $this->timestampString($report->created_at),
                        'updated_at' => $this->timestampString($report->updated_at),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function replaceBranchModel(string $branchCode, array $model): void
    {
        $branchCode = normalize_public_branch_code($branchCode);
        $branchId = branch_id_from_slug($branchCode);
        if ($branchCode === '' || $branchId === null) {
            return;
        }

        DB::transaction(function () use ($branchId, $model): void {
            DiscipleshipRelationship::query()->where('branch_id', $branchId)->delete();
            DiscipleshipGroupPerson::query()->where('branch_id', $branchId)->delete();

            $personMap = $this->syncPeople($branchId, $model['discipleship_persons'] ?? []);
            $groupMap = $this->syncGroups($branchId, $model['discipleship_groups'] ?? [], $personMap);

            $this->insertRelationships($branchId, $model['discipleship_relations'] ?? [], $personMap, $groupMap);
            $this->insertGroupPeople($branchId, $model['group_memberships'] ?? [], $personMap, $groupMap, 'member');
            $this->insertGroupPeople($branchId, $model['group_leaderships'] ?? [], $personMap, $groupMap, 'leader');
            $this->applyMultiplications($branchId, $model['group_multiplications'] ?? [], $personMap, $groupMap);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function peopleRows(array $branchIds): array
    {
        return DiscipleshipPerson::query()
            ->whereIn('branch_id', $branchIds)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipPerson $person): array => [
                'id' => (string) $person->getKey(),
                'member_id' => (string) $person->getKey(),
                'branch_code' => $person->branch_code,
                'full_name' => trim((string) $person->full_name),
                'phone' => trim((string) $person->phone),
                'gender' => trim((string) $person->gender),
                'status' => trim((string) ($person->status ?? 'active')) ?: 'active',
                'notes' => trim((string) $person->notes),
                'campus' => trim((string) $person->campus),
                'major' => trim((string) $person->major),
                'occupation' => trim((string) $person->occupation),
                'created_at' => optional($person->created_at)->toIso8601String(),
                'updated_at' => optional($person->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function groupRows(array $branchIds): array
    {
        return DiscipleshipGroup::query()
            ->whereIn('branch_id', $branchIds)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipGroup $group): array => [
                'id' => (string) $group->getKey(),
                'branch_code' => $group->branch_code,
                'name' => trim((string) $group->name),
                'status' => trim((string) ($group->status ?? 'active')) ?: 'active',
                'start_stage' => normalize_dg_progress_value((string) $group->start_stage),
                'current_stage' => normalize_dg_progress_value((string) $group->current_stage),
                'parent_group_id' => $group->parent_group_id !== null ? (string) $group->parent_group_id : '',
                'source_group_id' => $group->source_group_id !== null ? (string) $group->source_group_id : '',
                'initiated_by_person_id' => $group->initiated_by_person_id !== null ? (string) $group->initiated_by_person_id : '',
                'multiplied_at' => optional($group->multiplied_at)->format('Y-m-d'),
                'notes' => trim((string) $group->notes),
                'created_at' => optional($group->created_at)->toIso8601String(),
                'updated_at' => optional($group->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function relationshipRows(array $branchIds): array
    {
        return DiscipleshipRelationship::query()
            ->whereIn('branch_id', $branchIds)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipRelationship $relation): array => [
                'id' => (string) $relation->getKey(),
                'branch_code' => $relation->branch_code,
                'mentor_person_id' => $relation->mentor_person_id !== null ? (string) $relation->mentor_person_id : '',
                'disciple_person_id' => $relation->disciple_person_id !== null ? (string) $relation->disciple_person_id : '',
                'context_group_id' => $relation->context_group_id !== null ? (string) $relation->context_group_id : '',
                'relation_type' => trim((string) ($relation->relation_type ?? 'discipleship')),
                'stage_at_start' => normalize_dg_progress_value((string) $relation->stage_at_start),
                'status' => trim((string) ($relation->status ?? 'active')) ?: 'active',
                'start_date' => optional($relation->start_date)->format('Y-m-d'),
                'end_date' => optional($relation->end_date)->format('Y-m-d'),
                'reason_end' => trim((string) $relation->reason_end),
                'notes' => trim((string) $relation->notes),
                'created_at' => optional($relation->created_at)->toIso8601String(),
                'updated_at' => optional($relation->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function groupPeopleRows(array $branchIds): array
    {
        return DiscipleshipGroupPerson::query()
            ->whereIn('branch_id', $branchIds)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipGroupPerson $row): array => [
                'id' => (string) $row->getKey(),
                'branch_code' => $row->branch_code,
                'group_id' => (string) $row->discipleship_group_id,
                'person_id' => $row->person_id !== null ? (string) $row->person_id : '',
                'leader_person_id' => $row->person_id !== null ? (string) $row->person_id : '',
                'role' => trim((string) $row->role),
                'stage' => normalize_dg_progress_value((string) $row->stage),
                'status' => trim((string) ($row->status ?? 'active')) ?: 'active',
                'start_date' => optional($row->started_on)->format('Y-m-d'),
                'end_date' => optional($row->ended_on)->format('Y-m-d'),
                'reason_end' => trim((string) $row->end_reason),
                'reason_change' => trim((string) $row->end_reason),
                'created_at' => optional($row->created_at)->toIso8601String(),
                'updated_at' => optional($row->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function multiplicationRows(array $groups): array
    {
        $rows = [];
        foreach ($groups as $group) {
            $sourceGroupId = trim((string) ($group['source_group_id'] ?? ''));
            $initiatorId = trim((string) ($group['initiated_by_person_id'] ?? ''));
            $multipliedAt = trim((string) ($group['multiplied_at'] ?? ''));
            if ($sourceGroupId === '' && $initiatorId === '' && $multipliedAt === '') {
                continue;
            }

            $groupId = (string) ($group['id'] ?? '');
            $rows[] = [
                'id' => 'multiplication-'.$groupId,
                'initiated_by_person_id' => $initiatorId,
                'source_group_id' => $sourceGroupId,
                'new_group_id' => $groupId,
                'multiplication_date' => $multipliedAt,
                'notes' => trim((string) ($group['notes'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, int>
     */
    private function syncPeople(int $branchId, array $rows): array
    {
        $map = [];
        $kept = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sourceId = trim((string) ($row['id'] ?? ''));
            $person = ctype_digit($sourceId)
                ? DiscipleshipPerson::query()->where('branch_id', $branchId)->whereKey((int) $sourceId)->first()
                : null;
            $person ??= new DiscipleshipPerson;
            $person->fill([
                'branch_id' => $branchId,
                'full_name' => $this->nullableString($row['full_name'] ?? $row['name'] ?? null),
                'phone' => $this->nullableString($row['phone'] ?? $row['whatsapp'] ?? null),
                'gender' => $this->nullableString($row['gender'] ?? null),
                'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                'notes' => $this->nullableString($row['notes'] ?? null),
                'campus' => $this->nullableString($row['campus'] ?? null),
                'major' => $this->nullableString($row['major'] ?? null),
                'occupation' => $this->nullableString($row['occupation'] ?? null),
            ]);
            $person->save();

            $actualId = (int) $person->getKey();
            $map[$sourceId !== '' ? $sourceId : (string) $actualId] = $actualId;
            $map[(string) $actualId] = $actualId;
            $kept[] = $actualId;
        }

        DiscipleshipPerson::query()
            ->where('branch_id', $branchId)
            ->when($kept !== [], static fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();

        return $map;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  array<string, int>  $personMap
     * @return array<string, int>
     */
    private function syncGroups(int $branchId, array $rows, array $personMap): array
    {
        $map = [];
        $kept = [];
        $pending = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sourceId = trim((string) ($row['id'] ?? ''));
            $group = ctype_digit($sourceId)
                ? DiscipleshipGroup::query()->where('branch_id', $branchId)->whereKey((int) $sourceId)->first()
                : null;
            $group ??= new DiscipleshipGroup;
            $group->fill([
                'branch_id' => $branchId,
                'name' => $this->nullableString($row['name'] ?? null) ?? 'Kelompok',
                'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                'start_stage' => $this->normalizedProgress($row['start_stage'] ?? null),
                'current_stage' => $this->normalizedProgress($row['current_stage'] ?? $row['progress'] ?? null),
                'notes' => $this->nullableString($row['notes'] ?? null),
            ]);
            $group->save();

            $actualId = (int) $group->getKey();
            $map[$sourceId !== '' ? $sourceId : (string) $actualId] = $actualId;
            $map[(string) $actualId] = $actualId;
            $kept[] = $actualId;
            $pending[$actualId] = $row;
        }

        foreach ($pending as $groupId => $row) {
            DiscipleshipGroup::query()->whereKey($groupId)->update([
                'parent_group_id' => $this->mappedId($map, $row['parent_group_id'] ?? null),
                'source_group_id' => $this->mappedId($map, $row['source_group_id'] ?? null),
                'initiated_by_person_id' => $this->mappedId($personMap, $row['initiated_by_person_id'] ?? null),
                'multiplied_at' => $this->dateValue($row['multiplied_at'] ?? $row['multiplication_date'] ?? null),
            ]);
        }

        DiscipleshipGroup::query()
            ->where('branch_id', $branchId)
            ->when($kept !== [], static fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();

        return $map;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  array<string, int>  $personMap
     * @param  array<string, int>  $groupMap
     */
    private function insertRelationships(int $branchId, array $rows, array $personMap, array $groupMap): void
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $discipleId = $this->mappedId($personMap, $row['disciple_person_id'] ?? null);
            if ($discipleId === null) {
                continue;
            }

            DiscipleshipRelationship::query()->create([
                'branch_id' => $branchId,
                'mentor_person_id' => $this->mappedId($personMap, $row['mentor_person_id'] ?? null),
                'disciple_person_id' => $discipleId,
                'context_group_id' => $this->mappedId($groupMap, $row['context_group_id'] ?? null),
                'relation_type' => $this->nullableString($row['relation_type'] ?? null) ?? 'discipleship',
                'stage_at_start' => $this->normalizedProgress($row['stage_at_start'] ?? null),
                'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                'start_date' => $this->dateValue($row['start_date'] ?? null),
                'end_date' => $this->dateValue($row['end_date'] ?? null),
                'reason_end' => $this->nullableString($row['reason_end'] ?? null),
                'notes' => $this->nullableString($row['notes'] ?? null),
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  array<string, int>  $personMap
     * @param  array<string, int>  $groupMap
     */
    private function insertGroupPeople(int $branchId, array $rows, array $personMap, array $groupMap, string $defaultRole): void
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $groupId = $this->mappedId($groupMap, $row['group_id'] ?? null);
            $personId = $this->mappedId($personMap, $row['person_id'] ?? $row['leader_person_id'] ?? null);
            if ($groupId === null || $personId === null) {
                continue;
            }

            DiscipleshipGroupPerson::query()->create([
                'branch_id' => $branchId,
                'discipleship_group_id' => $groupId,
                'person_id' => $personId,
                'role' => $this->nullableString($row['role'] ?? null) ?? $defaultRole,
                'stage' => $this->normalizedProgress($row['stage'] ?? null),
                'status' => $this->nullableString($row['status'] ?? null) ?? 'active',
                'started_on' => $this->dateValue($row['start_date'] ?? $row['started_on'] ?? null),
                'ended_on' => $this->dateValue($row['end_date'] ?? $row['ended_on'] ?? null),
                'end_reason' => $this->nullableString($row['reason_end'] ?? $row['reason_change'] ?? null),
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  array<string, int>  $personMap
     * @param  array<string, int>  $groupMap
     */
    private function applyMultiplications(int $branchId, array $rows, array $personMap, array $groupMap): void
    {
        DiscipleshipGroup::query()->where('branch_id', $branchId)->update([
            'source_group_id' => null,
            'initiated_by_person_id' => null,
            'multiplied_at' => null,
        ]);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $newGroupId = $this->mappedId($groupMap, $row['new_group_id'] ?? null);
            if ($newGroupId === null) {
                continue;
            }

            DiscipleshipGroup::query()->where('branch_id', $branchId)->whereKey($newGroupId)->update([
                'source_group_id' => $this->mappedId($groupMap, $row['source_group_id'] ?? null),
                'initiated_by_person_id' => $this->mappedId($personMap, $row['initiated_by_person_id'] ?? null),
                'multiplied_at' => $this->dateValue($row['multiplication_date'] ?? null),
            ]);
        }
    }

    /** @param array<int, string> $branchCodes */
    private function normalizeBranchCodes(array $branchCodes): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $branchCode): string => normalize_public_branch_code($branchCode),
            $branchCodes,
        ))));
    }

    /** @param iterable<int, array<string, mixed>> $rows */
    private function reportPersonIds(iterable $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['person_id'] ?? 0);
            if ($id > 0 && ! in_array((string) $id, $ids, true)) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    /** @param iterable<int, array<string, mixed>> $photos */
    private function reportPhotos(iterable $photos): array
    {
        $rows = [];
        foreach ($photos as $photo) {
            $path = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($path !== '') {
                $rows[] = [
                    'path' => $path,
                    'name' => trim((string) ($photo['name'] ?? '')) ?: basename($path),
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, int> $map */
    private function mappedId(array $map, mixed $sourceId): ?int
    {
        $sourceId = trim((string) $sourceId);
        if ($sourceId === '' || $sourceId === 'virtual_injil') {
            return null;
        }

        return $map[$sourceId] ?? null;
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizedProgress(mixed $value): ?string
    {
        $value = normalize_dg_progress_value((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
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
