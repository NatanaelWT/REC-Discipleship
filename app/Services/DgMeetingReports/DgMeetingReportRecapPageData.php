<?php

namespace App\Services\DgMeetingReports;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipGroupLeadership;
use App\Models\DiscipleshipGroupMembership;
use App\Models\DiscipleshipMeetingReport;
use App\Models\DiscipleshipMeetingReportAbsence;
use App\Models\DiscipleshipMeetingReportMeditationSharer;
use App\Models\DiscipleshipMeetingReportPhoto;
use App\Models\DiscipleshipPerson;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DgMeetingReportRecapPageData
{
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
        $branchLabels = $this->branchLabels();
        $people = $this->people($branchCodes, $centralReadOnly, $branchLabels);

        return [
            'settings' => ['church_name' => app_church_name()],
            'page' => 'dg_reports_recap',
            'people' => array_values($people),
            'groups' => $this->groups($branchCodes, $centralReadOnly, $branchLabels, $people),
            'dgMeetingReports' => $this->reports($branchCodes, $centralReadOnly, $branchLabels, $people),
        ];
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
     * @param array<int, string> $branchCodes
     * @param array<string, string> $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function people(array $branchCodes, bool $centralReadOnly, array $branchLabels): array
    {
        if (! Schema::hasTable('discipleship_people')) {
            return [];
        }

        $people = [];
        foreach (DiscipleshipPerson::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->orderBy('id')->get() as $person) {
            $branchCode = normalize_public_branch_code((string) $person->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $person->public_id);
            if ($effectiveId === '') {
                continue;
            }

            $name = trim((string) ($person->full_name ?? ''));
            if ($name === '') {
                $name = '-';
            }
            if ($centralReadOnly) {
                $name = append_branch_suffix($name, $branchLabels[$branchCode] ?? strtoupper($branchCode));
            }

            $people[$effectiveId] = [
                'id' => $effectiveId,
                'public_id' => (string) $person->public_id,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'name' => $name,
                'full_name' => $name,
                'member_id' => trim((string) ($person->member_public_id ?? '')),
                'member_public_id' => trim((string) ($person->member_public_id ?? '')),
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
     * @param array<int, string> $branchCodes
     * @param array<string, string> $branchLabels
     * @param array<string, array<string, mixed>> $people
     * @return array<int, array<string, mixed>>
     */
    private function groups(array $branchCodes, bool $centralReadOnly, array $branchLabels, array $people): array
    {
        if (! Schema::hasTable('discipleship_groups')) {
            return [];
        }

        $groups = DiscipleshipGroup::query()
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->orderBy('id')
            ->get();

        $leadershipsByGroup = $this->leadershipsByGroup($branchCodes);
        $membershipsByGroup = $this->membershipsByGroup($branchCodes);
        $rows = [];

        foreach ($groups as $group) {
            $branchCode = normalize_public_branch_code((string) $group->branch_code);
            $groupId = $this->effectiveId($branchCode, (string) $group->public_id);
            if ($groupId === '') {
                continue;
            }

            $leaderId = $this->currentLeaderId($branchCode, $leadershipsByGroup[(string) $group->public_id] ?? []);
            $memberIds = $this->activeMemberIds($branchCode, $membershipsByGroup[(string) $group->public_id] ?? []);
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
                'public_id' => (string) $group->public_id,
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
     * @param array<int, string> $branchCodes
     * @return array<string, array<int, DiscipleshipGroupLeadership>>
     */
    private function leadershipsByGroup(array $branchCodes): array
    {
        if (! Schema::hasTable('discipleship_group_people') && ! Schema::hasTable('discipleship_group_leaderships')) {
            return [];
        }

        $rows = [];
        $query = Schema::hasTable('discipleship_group_people')
            ? DiscipleshipGroupPerson::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->where('role', '!=', 'member')->orderBy('id')
            : DiscipleshipGroupLeadership::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->orderBy('id');

        foreach ($query->get() as $leadership) {
            $groupPublicId = trim((string) ($leadership->group_public_id ?? ''));
            if ($groupPublicId !== '') {
                $rows[$groupPublicId][] = $leadership;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<string, array<int, DiscipleshipGroupMembership>>
     */
    private function membershipsByGroup(array $branchCodes): array
    {
        if (! Schema::hasTable('discipleship_group_people') && ! Schema::hasTable('discipleship_group_memberships')) {
            return [];
        }

        $rows = [];
        $query = Schema::hasTable('discipleship_group_people')
            ? DiscipleshipGroupPerson::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->where('role', 'member')->orderBy('id')
            : DiscipleshipGroupMembership::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->orderBy('id');

        foreach ($query->get() as $membership) {
            $groupPublicId = trim((string) ($membership->group_public_id ?? ''));
            if ($groupPublicId !== '') {
                $rows[$groupPublicId][] = $membership;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, DiscipleshipGroupLeadership> $leaderships
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

        return $this->effectiveId($branchCode, (string) ($selected['person_public_id'] ?? ''));
    }

    /**
     * @param array<int, DiscipleshipGroupMembership> $memberships
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

            $personId = $this->effectiveId($branchCode, (string) ($membership->person_public_id ?? ''));
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
            'person_public_id' => (string) ($row->person_public_id ?? ''),
            'status' => strtolower(trim((string) ($row->status ?? 'active'))) ?: 'active',
            'start_date' => $this->dateString($row->started_on ?? $row->start_date ?? null),
            'end_date' => $this->dateString($row->ended_on ?? $row->end_date ?? null),
            'created_at' => $this->timestampString($row->created_at ?? null),
            'updated_at' => $this->timestampString($row->updated_at ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function periodRowSort(array $row): string
    {
        return trim((string) ($row['end_date'] ?? ''))
            ?: (trim((string) ($row['start_date'] ?? ''))
            ?: (trim((string) ($row['updated_at'] ?? '')) ?: trim((string) ($row['created_at'] ?? ''))));
    }

    /**
     * @param array<int, string> $branchCodes
     * @param array<string, string> $branchLabels
     * @param array<string, array<string, mixed>> $people
     * @return array<int, array<string, mixed>>
     */
    private function reports(array $branchCodes, bool $centralReadOnly, array $branchLabels, array $people): array
    {
        if (! Schema::hasTable('discipleship_meeting_reports')) {
            return [];
        }

        $reports = DiscipleshipMeetingReport::query()
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->orderByDesc('meeting_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $rows = [];
        foreach ($reports as $report) {
            $branchCode = normalize_public_branch_code((string) $report->branch_code);
            $branchLabel = $branchLabels[$branchCode] ?? strtoupper($branchCode);
            $leaderId = $this->effectiveId($branchCode, (string) ($report->leader_person_public_id ?? ''));
            $groupId = $this->effectiveId($branchCode, (string) ($report->discipleship_group_public_id ?? ''));
            $leaderName = trim((string) ($report->leader_name_snapshot ?? ''));
            $groupName = trim((string) ($report->group_name_snapshot ?? 'Kelompok')) ?: 'Kelompok';
            if ($centralReadOnly) {
                if ($leaderName !== '') {
                    $leaderName = append_branch_suffix($leaderName, $branchLabel);
                }
                $groupName = append_branch_suffix($groupName, $branchLabel);
            }

            $rows[] = [
                'id' => $this->effectiveId($branchCode, (string) $report->public_id),
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
                'absent_member_ids' => $this->personPublicIds($branchCode, $report->absenceItems()),
                'absent_member_names' => $this->personNames($branchCode, $centralReadOnly, $branchLabel, $people, $report->absenceItems()),
                'additional_notes' => trim((string) ($report->additional_notes ?? '')),
                'meditation_min_times' => max(0, (int) $report->meditation_min_times),
                'meditation_sharer_ids' => $this->personPublicIds($branchCode, $report->meditationSharerItems()),
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
     * @param iterable<int, DiscipleshipMeetingReportAbsence|DiscipleshipMeetingReportMeditationSharer> $peopleRows
     * @return array<int, string>
     */
    private function personPublicIds(string $branchCode, iterable $peopleRows): array
    {
        $ids = [];
        foreach ($peopleRows as $row) {
            $personId = $this->effectiveId($branchCode, $this->rowString($row, 'person_public_id'));
            if ($personId !== '' && ! in_array($personId, $ids, true)) {
                $ids[] = $personId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, array<string, mixed>> $people
     * @param iterable<int, DiscipleshipMeetingReportAbsence|DiscipleshipMeetingReportMeditationSharer> $peopleRows
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
            $personId = $this->effectiveId($branchCode, $this->rowString($row, 'person_public_id'));
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
     * @param iterable<int, DiscipleshipMeetingReportPhoto> $photos
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

    private function effectiveId(string $branchCode, string $publicId): string
    {
        $branchCode = normalize_public_branch_code($branchCode);
        $publicId = trim($publicId);
        if ($publicId === '') {
            return '';
        }

        return is_effective_central_discipleship_readonly()
            ? scoped_virtual_id($branchCode, $publicId)
            : $publicId;
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
