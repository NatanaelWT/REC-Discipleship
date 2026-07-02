<?php

namespace App\Services\DgMeetingReports;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipMeetingReport;
use App\Models\DiscipleshipPerson;
use App\Services\Discipleship\DiscipleshipReadCache;
use App\Support\DiscipleshipPersonProfile;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DgMeetingReportRecapPageData
{
    public function __construct(private readonly DiscipleshipReadCache $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function forCurrentContext(Request $request): array
    {
        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedBranch = $centralReadOnly
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_public_branch_code(current_user_branch());

        $branchCodes = $this->branchCodes($selectedBranch, $centralReadOnly);
        $data = $this->cache->remember('meeting-report-recap', [...$branchCodes, $centralReadOnly ? 'central' : 'branch'], function () use ($branchCodes, $centralReadOnly): array {
            $branchLabels = $this->branchLabels();
            [$leadershipsByGroup, $membershipsByGroup] = $this->groupPeopleByGroup($branchCodes);
            $people = $this->people($branchCodes, $centralReadOnly, $branchLabels, $this->personIdsFromGroupPeople($leadershipsByGroup, $membershipsByGroup));

            return [
                'page' => 'dg_reports_recap',
                'people' => array_values($people),
                'groups' => $this->groups($branchCodes, $centralReadOnly, $branchLabels, $people, $leadershipsByGroup, $membershipsByGroup),
                'dgMeetingReports' => $this->reports($branchCodes, $centralReadOnly, $branchLabels, $people),
            ];
        });
        $data['settings'] = ['church_name' => app_church_name()];

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function branchCodes(string $selectedBranch, bool $centralReadOnly): array
    {
        if ($centralReadOnly && $selectedBranch === 'all') {
            return array_values(array_filter(array_map(
                static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? '')),
                public_dg_branch_options(),
            ), static fn (string $branchCode): bool => $branchCode !== ''));
        }

        return [$selectedBranch];
    }

    /**
     * @return array<string, string>
     */
    private function branchLabels(): array
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
     * @param  array<int, string>  $branchCodes
     * @param  array<string, string>  $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function people(array $branchCodes, bool $centralReadOnly, array $branchLabels, array $extraPersonIds = []): array
    {
        $people = [];
        $branchIds = branch_ids_from_slugs($branchCodes);
        try {
            $query = DiscipleshipPerson::query();
            DiscipleshipPersonProfile::join($query);

            $records = $query
                ->where(function ($query) use ($branchIds, $extraPersonIds): void {
                    $query->whereIn('discipleship_people.branch_id', $branchIds);
                    if ($extraPersonIds !== []) {
                        $query->orWhereIn('discipleship_people.id', $extraPersonIds);
                    }
                })
                ->orderBy('discipleship_people.id')
                ->get([
                    'discipleship_people.id',
                    'discipleship_people.branch_id',
                    'discipleship_people.status',
                    'discipleship_people.notes',
                    'discipleship_people.created_at',
                    'discipleship_people.updated_at',
                    DB::raw(DiscipleshipPersonProfile::expression('full_name').' as full_name'),
                    DB::raw(DiscipleshipPersonProfile::expression('phone').' as phone'),
                    DB::raw(DiscipleshipPersonProfile::expression('gender').' as gender'),
                ]);
        } catch (Throwable) {
            return [];
        }
        $singleContextBranchCode = count($branchCodes) === 1 ? normalize_public_branch_code((string) $branchCodes[0]) : '';
        foreach ($records as $person) {
            $branchCode = normalize_public_branch_code((string) $person->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $person->getKey());
            if ($effectiveId === '') {
                continue;
            }

            $name = trim((string) ($person->full_name ?? ''));
            if ($name === '') {
                $name = '-';
            }
            if ($centralReadOnly || ($singleContextBranchCode !== '' && $branchCode !== '' && $branchCode !== $singleContextBranchCode)) {
                $name = append_branch_suffix($name, $branchLabels[$branchCode] ?? strtoupper($branchCode));
            }

            $people[$effectiveId] = [
                'id' => $effectiveId,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'name' => $name,
                'full_name' => $name,
                'member_id' => $effectiveId,
                'phone' => trim((string) ($person->phone ?? '')),
                'gender' => trim((string) ($person->gender ?? '')),
                'status' => trim((string) ($person->status ?? 'active')) ?: 'active',
                'notes' => trim((string) ($person->notes ?? '')),
                'created_at' => $this->timestampString($person->created_at ?? null),
                'updated_at' => $this->timestampString($person->updated_at ?? null),
            ];
        }

        return $people;
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @param  array<string, string>  $branchLabels
     * @param  array<string, array<string, mixed>>  $people
     * @return array<int, array<string, mixed>>
     */
    private function groups(
        array $branchCodes,
        bool $centralReadOnly,
        array $branchLabels,
        array $people,
        array $leadershipsByGroup,
        array $membershipsByGroup,
    ): array {
        try {
            $groups = DiscipleshipGroup::query()
                ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
                ->where('status', 'active')
                ->orderBy('id')
                ->get(['id', 'branch_id', 'name', 'status', 'start_stage', 'current_stage', 'created_at', 'updated_at']);
        } catch (Throwable) {
            return [];
        }
        $rows = [];

        foreach ($groups as $group) {
            $branchCode = normalize_public_branch_code((string) $group->branch_code);
            $groupId = $this->effectiveId($branchCode, (string) $group->getKey());
            if ($groupId === '') {
                continue;
            }

            $leaderId = $this->currentLeaderId($branchCode, $leadershipsByGroup[(string) $group->getKey()] ?? []);
            $memberIds = $this->activeMemberIds($branchCode, $membershipsByGroup[(string) $group->getKey()] ?? []);
            $memberNames = [];
            foreach ($memberIds as $memberId) {
                $memberNames[$memberId] = trim((string) ($people[$memberId]['name'] ?? ''));
            }

            $name = trim((string) ($group->name ?? 'Kelompok')) ?: 'Kelompok';
            if ($centralReadOnly) {
                $name = append_branch_suffix($name, $branchLabels[$branchCode] ?? strtoupper($branchCode));
            }

            $rows[] = [
                'id' => $groupId,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'name' => $name,
                'leader_id' => $leaderId,
                'member_ids' => $memberIds,
                'member_names' => $memberNames,
                'progress' => normalize_dg_progress_value((string) ($group->current_stage ?? $group->start_stage ?? '')) ?: 'DG 1',
                'status' => strtolower(trim((string) ($group->status ?? 'active'))) ?: 'active',
                'created_at' => $this->timestampString($group->created_at ?? null),
                'updated_at' => $this->timestampString($group->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<string, array<int, DiscipleshipGroupPerson>>
     */
    private function groupPeopleByGroup(array $branchCodes): array
    {
        $leaderships = [];
        $memberships = [];
        try {
            $records = DiscipleshipGroupPerson::query()
                ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
                ->orderBy('id')
                ->get(['id', 'branch_id', 'discipleship_group_id', 'person_id', 'role', 'stage', 'status', 'started_on', 'ended_on', 'end_reason']);
        } catch (Throwable) {
            return [$leaderships, $memberships];
        }

        foreach ($records as $record) {
            $groupId = (int) ($record->discipleship_group_id ?? 0);
            if ($groupId > 0) {
                if (($record->role ?? '') === 'member') {
                    $memberships[(string) $groupId][] = $record;
                } else {
                    $leaderships[(string) $groupId][] = $record;
                }
            }
        }

        return [$leaderships, $memberships];
    }

    /**
     * @param array<string, array<int, DiscipleshipGroupPerson>> $leadershipsByGroup
     * @param array<string, array<int, DiscipleshipGroupPerson>> $membershipsByGroup
     * @return array<int, int>
     */
    private function personIdsFromGroupPeople(array $leadershipsByGroup, array $membershipsByGroup): array
    {
        $ids = [];
        foreach (array_merge($leadershipsByGroup, $membershipsByGroup) as $records) {
            foreach ($records as $record) {
                $personId = (int) ($record->person_id ?? 0);
                if ($personId > 0) {
                    $ids[$personId] = $personId;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param  array<int, DiscipleshipGroupPerson>  $leaderships
     */
    private function currentLeaderId(string $branchCode, array $leaderships): string
    {
        $selected = null;
        $fallback = null;
        foreach ($leaderships as $leadership) {
            $role = strtolower(trim((string) ($leadership->role ?? 'leader')));
            if (in_array($role, ['co_leader', 'assistant', 'pendamping'], true)) {
                continue;
            }

            $row = $this->periodRow($leadership);
            if (dgv2_is_current_period($row) && ($selected === null || $this->periodRowSort($row) > $this->periodRowSort($selected))) {
                $selected = $row;
            }
            if ($fallback === null || $this->periodRowSort($row) > $this->periodRowSort($fallback)) {
                $fallback = $row;
            }
        }

        $selected ??= $fallback;
        if (! is_array($selected)) {
            return '';
        }

        return $this->effectiveId($branchCode, (string) ($selected['person_id'] ?? ''));
    }

    /**
     * @param  array<int, DiscipleshipGroupPerson>  $memberships
     * @return array<int, string>
     */
    private function activeMemberIds(string $branchCode, array $memberships): array
    {
        $ids = [];
        foreach ($memberships as $membership) {
            $row = $this->periodRow($membership);
            if (! dgv2_is_current_period($row)) {
                continue;
            }

            $personId = $this->effectiveId($branchCode, (string) ($membership->person_id ?? ''));
            if ($personId !== '' && ! in_array($personId, $ids, true)) {
                $ids[] = $personId;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function periodRow(object $row): array
    {
        return [
            'person_id' => (string) ($row->person_id ?? ''),
            'status' => strtolower(trim((string) ($row->status ?? 'active'))) ?: 'active',
            'start_date' => $this->dateString($row->started_on ?? $row->start_date ?? null),
            'end_date' => $this->dateString($row->ended_on ?? $row->end_date ?? null),
            'created_at' => $this->timestampString($row->created_at ?? null),
            'updated_at' => $this->timestampString($row->updated_at ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function periodRowSort(array $row): string
    {
        return trim((string) ($row['end_date'] ?? ''))
            ?: (trim((string) ($row['start_date'] ?? ''))
            ?: (trim((string) ($row['updated_at'] ?? '')) ?: trim((string) ($row['created_at'] ?? ''))));
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @param  array<string, string>  $branchLabels
     * @param  array<string, array<string, mixed>>  $people
     * @return array<int, array<string, mixed>>
     */
    private function reports(array $branchCodes, bool $centralReadOnly, array $branchLabels, array $people): array
    {
        try {
            $reports = DiscipleshipMeetingReport::query()
                ->select([
                    'id', 'branch_id', 'leader_person_id', 'leader_name_snapshot', 'discipleship_group_id',
                    'group_name_snapshot', 'meeting_date', 'material_topic', 'group_progress_snapshot',
                    'absence_reason', 'absences', 'meditation_sharers', 'photos', 'additional_notes',
                    'meditation_min_times', 'sharing_openness_score', 'prepared_material', 'prayed_for_members',
                    'shared_meditation', 'relationally_contacted', 'source', 'created_at', 'updated_at',
                ])
                ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
                ->whereExists(static function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('discipleship_groups as report_group')
                        ->whereColumn('report_group.id', 'discipleship_meeting_reports.discipleship_group_id')
                        ->whereColumn('report_group.branch_id', 'discipleship_meeting_reports.branch_id')
                        ->where('report_group.status', 'active');
                })
                ->orderByDesc('meeting_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
        } catch (Throwable) {
            return [];
        }

        $rows = [];
        foreach ($reports as $report) {
            $branchCode = normalize_public_branch_code((string) $report->branch_code);
            $branchLabel = $branchLabels[$branchCode] ?? strtoupper($branchCode);
            $leaderId = $this->effectiveId($branchCode, (string) ($report->leader_person_id ?? ''));
            $groupId = $this->effectiveId($branchCode, (string) ($report->discipleship_group_id ?? ''));
            $leaderName = trim((string) ($report->leader_name_snapshot ?? ''));
            $groupName = trim((string) ($report->group_name_snapshot ?? 'Kelompok')) ?: 'Kelompok';
            if ($centralReadOnly) {
                if ($leaderName !== '') {
                    $leaderName = append_branch_suffix($leaderName, $branchLabel);
                }
                $groupName = append_branch_suffix($groupName, $branchLabel);
            }

            $rows[] = [
                'id' => $this->effectiveId($branchCode, (string) $report->getKey()),
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
                'absent_member_ids' => $this->personIds($branchCode, $report->absenceItems()),
                'absent_member_names' => $this->personNames($branchCode, $centralReadOnly, $branchLabel, $people, $report->absenceItems()),
                'additional_notes' => trim((string) ($report->additional_notes ?? '')),
                'meditation_min_times' => max(0, (int) $report->meditation_min_times),
                'meditation_sharer_ids' => $this->personIds($branchCode, $report->meditationSharerItems()),
                'meditation_sharer_names' => $this->personNames($branchCode, $centralReadOnly, $branchLabel, $people, $report->meditationSharerItems()),
                'meeting_photos' => $this->photos($report->photoItems()),
                'quality_pray' => $report->prayed_for_members ? 'true' : 'false',
                'quality_prepare' => $report->prepared_material ? 'true' : 'false',
                'quality_relational' => $report->relationally_contacted ? 'true' : 'false',
                'quality_share_meditation' => $report->shared_meditation ? 'true' : 'false',
                'sharing_openness' => $report->sharing_openness_score,
                'source' => trim((string) ($report->source ?? 'public_form')) ?: 'public_form',
                'created_at' => $this->timestampString($report->created_at ?? null),
                'updated_at' => $this->timestampString($report->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $peopleRows
     * @return array<int, string>
     */
    private function personIds(string $branchCode, iterable $peopleRows): array
    {
        $ids = [];
        foreach ($peopleRows as $row) {
            $personId = $this->effectiveId($branchCode, $this->rowString($row, 'person_id'));
            if ($personId !== '' && ! in_array($personId, $ids, true)) {
                $ids[] = $personId;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, array<string, mixed>>  $people
     * @param  iterable<int, array<string, mixed>>  $peopleRows
     * @return array<int, string>
     */
    private function personNames(
        string $branchCode,
        bool $centralReadOnly,
        string $branchLabel,
        array $people,
        iterable $peopleRows,
    ): array {
        $names = [];
        foreach ($peopleRows as $row) {
            $personId = $this->effectiveId($branchCode, $this->rowString($row, 'person_id'));
            $name = trim((string) ($people[$personId]['name'] ?? ''));
            if ($name === '') {
                $name = $this->rowString($row, 'person_name_snapshot');
                if ($centralReadOnly && $name !== '') {
                    $name = append_branch_suffix($name, $branchLabel);
                }
            }

            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $photos
     * @return array<int, array{path: string, name: string}>
     */
    private function photos(iterable $photos): array
    {
        $rows = [];
        foreach ($photos as $photo) {
            $path = sanitize_relative_upload_path($this->rowString($photo, 'path') ?: $this->rowString($photo, 'relative_path'));
            if ($path === '') {
                continue;
            }

            $rows[] = [
                'path' => $path,
                'name' => trim($this->rowString($photo, 'name') ?: $this->rowString($photo, 'original_file_name')) ?: basename($path),
            ];
        }

        return $rows;
    }

    private function rowString(mixed $row, string $key): string
    {
        if (is_array($row)) {
            return trim((string) ($row[$key] ?? ''));
        }

        if (is_object($row)) {
            return trim((string) ($row->{$key} ?? ''));
        }

        return '';
    }

    private function effectiveId(string $branchCode, string $id): string
    {
        return trim($id);
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
