<?php

namespace App\Services\DiscipleshipGroups;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupLeadership;
use App\Models\DiscipleshipGroupMembership;
use App\Models\DiscipleshipPerson;

class DiscipleshipGroupPublicFormData
{
    /**
     * @return array{leaders: array<int, array<string, string>>, groups: array<int, array<string, mixed>>, group_map: array<string, array<string, mixed>>}
     */
    public function forBranch(string $branchCode): array
    {
        $branchCode = normalize_public_branch_code($branchCode);
        if ($branchCode === '') {
            return ['leaders' => [], 'groups' => [], 'group_map' => []];
        }

        $groupsById = [];
        $leadersById = [];

        DiscipleshipGroup::query()
            ->with([
                'leaderships.person',
                'memberships.person',
            ])
            ->where('branch_code', $branchCode)
            ->where('status', 'active')
            ->orderBy('current_stage')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->each(function (DiscipleshipGroup $group) use (&$groupsById, &$leadersById): void {
                $groupId = trim((string) $group->public_id);
                if ($groupId === '') {
                    return;
                }

                $leader = $this->leaderForGroup($group);
                if ($leader === null) {
                    return;
                }

                $leaderId = trim((string) $leader->public_id);
                $leaderName = trim((string) $leader->full_name);
                if ($leaderId === '' || $leaderName === '') {
                    return;
                }

                $leadersById[$leaderId] ??= [
                    'id' => $leaderId,
                    'name' => $leaderName,
                ];

                $members = $this->memberRows($group);
                $progress = normalize_dg_progress_value((string) ($group->current_stage ?? $group->start_stage ?? ''));

                $groupsById[$groupId] = [
                    'id' => $groupId,
                    'leader_id' => $leaderId,
                    'leader_name' => $leaderName,
                    'name' => trim((string) ($group->name ?? 'Kelompok')) ?: 'Kelompok',
                    'progress' => $progress,
                    'members' => $members,
                ];
            });

        $leaders = array_values($leadersById);
        usort($leaders, static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        $groups = array_values($groupsById);
        usort($groups, static function (array $a, array $b): int {
            $leaderCompare = strcasecmp((string) ($a['leader_name'] ?? ''), (string) ($b['leader_name'] ?? ''));
            if ($leaderCompare !== 0) {
                return $leaderCompare;
            }

            $groupCompare = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            if ($groupCompare !== 0) {
                return $groupCompare;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        $groupMap = [];
        foreach ($groups as $groupRow) {
            $groupId = trim((string) ($groupRow['id'] ?? ''));
            if ($groupId !== '') {
                $groupMap[$groupId] = $groupRow;
            }
        }

        return [
            'leaders' => $leaders,
            'groups' => $groups,
            'group_map' => $groupMap,
        ];
    }

    private function leaderForGroup(DiscipleshipGroup $group): ?DiscipleshipPerson
    {
        $leaderships = $group->leaderships
            ->filter(static fn (object $leadership): bool => strtolower(trim((string) $leadership->status)) === 'active');

        $leader = $leaderships
            ->first(static fn (object $leadership): bool => strtolower(trim((string) $leadership->role)) === 'leader')
            ?? $leaderships->first();

        if (is_object($leader) && $leader->person instanceof DiscipleshipPerson) {
            return $leader->person;
        }

        $membershipLeader = $group->memberships
            ->filter(static fn (object $membership): bool => strtolower(trim((string) $membership->status)) === 'active')
            ->first(static fn (object $membership): bool => strtolower(trim((string) $membership->role)) === 'leader');

        return is_object($membershipLeader) && $membershipLeader->person instanceof DiscipleshipPerson
            ? $membershipLeader->person
            : null;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function memberRows(DiscipleshipGroup $group): array
    {
        $members = [];
        foreach ($group->memberships as $membership) {
            if (! is_object($membership)) {
                continue;
            }

            if (strtolower(trim((string) $membership->status)) !== 'active') {
                continue;
            }

            if (! $membership->person instanceof DiscipleshipPerson) {
                continue;
            }

            $memberId = trim((string) $membership->person->public_id);
            $memberName = trim((string) $membership->person->full_name);
            if ($memberId === '' || $memberName === '') {
                continue;
            }

            $members[$memberId] = [
                'id' => $memberId,
                'name' => $memberName,
            ];
        }

        $rows = array_values($members);
        usort($rows, static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $rows;
    }
}
