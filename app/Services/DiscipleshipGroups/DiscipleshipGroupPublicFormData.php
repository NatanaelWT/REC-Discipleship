<?php

namespace App\Services\DiscipleshipGroups;

use App\Models\DiscipleshipGroup;
use App\Models\Person;

class DiscipleshipGroupPublicFormData
{
    /**
     * @return array{leaders: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>, group_map: array<int, array<string, mixed>>}
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
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->where('status', 'active')
            ->orderBy('stage')
            ->orderBy('id')
            ->get()
            ->each(function (DiscipleshipGroup $group) use ($branchCode, &$groupsById, &$leadersById): void {
                $groupId = (int) $group->getKey();

                $leader = $this->leaderForGroup($group);
                if ($leader === null) {
                    return;
                }

                $leaderId = (int) $leader->getKey();
                $leaderName = trim((string) $leader->full_name);
                $leaderBranchCode = normalize_public_branch_code((string) $leader->branch_code);
                if ($leaderBranchCode !== '' && $leaderBranchCode !== $branchCode) {
                    $leaderName = append_branch_suffix($leaderName, public_branch_label($leaderBranchCode));
                }
                if ($leaderId < 1 || $leaderName === '') {
                    return;
                }

                $leadersById[$leaderId] ??= [
                    'id' => $leaderId,
                    'name' => $leaderName,
                ];

                $members = $this->memberRows($group);
                $progress = discipleship_group_stage_value($group) ?: 'DG 1';
                $groupLabel = discipleship_group_display_label([
                    'progress' => $progress,
                    'leader_name' => $leaderName,
                ]);

                $groupsById[$groupId] = [
                    'id' => $groupId,
                    'leader_id' => $leaderId,
                    'leader_name' => $leaderName,
                    'name' => $groupLabel,
                    'label' => $groupLabel,
                    'stage' => $progress,
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

            $groupCompare = strcasecmp((string) ($a['progress'] ?? ''), (string) ($b['progress'] ?? ''));
            if ($groupCompare !== 0) {
                return $groupCompare;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        $groupMap = [];
        foreach ($groups as $groupRow) {
            $groupId = (int) ($groupRow['id'] ?? 0);
            if ($groupId > 0) {
                $groupMap[$groupId] = $groupRow;
            }
        }

        return [
            'leaders' => $leaders,
            'groups' => $groups,
            'group_map' => $groupMap,
        ];
    }

    private function leaderForGroup(DiscipleshipGroup $group): ?Person
    {
        $leaderships = $group->leaderships
            ->filter(static fn (object $leadership): bool => strtolower(trim((string) $leadership->status)) === 'active');

        $leader = $leaderships
            ->first(static fn (object $leadership): bool => strtolower(trim((string) $leadership->role)) === 'leader')
            ?? $leaderships->first();

        if (is_object($leader) && $leader->person instanceof Person) {
            return $leader->person;
        }

        $membershipLeader = $group->memberships
            ->filter(static fn (object $membership): bool => strtolower(trim((string) $membership->status)) === 'active')
            ->first(static fn (object $membership): bool => strtolower(trim((string) $membership->role)) === 'leader');

        return is_object($membershipLeader) && $membershipLeader->person instanceof Person
            ? $membershipLeader->person
            : null;
    }

    /**
     * @return array<int, array{id: int, name: string}>
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

            if (! $membership->person instanceof Person) {
                continue;
            }

            $memberId = (int) $membership->person->getKey();
            $memberName = trim((string) $membership->person->full_name);
            if ($memberId < 1 || $memberName === '') {
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
