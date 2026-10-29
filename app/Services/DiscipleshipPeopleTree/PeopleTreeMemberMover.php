<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class PeopleTreeMemberMover
{
    public function move(int $branchId, int $personId, int $fromGroupId, int $toGroupId): ?string
    {
        if ($fromGroupId === $toGroupId) {
            return 'same_group';
        }

        return DB::transaction(function () use ($branchId, $personId, $fromGroupId, $toGroupId): ?string {
            $groups = DiscipleshipGroup::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', [$fromGroupId, $toGroupId])
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (DiscipleshipGroup $group): int => (int) $group->getKey());

            $sourceGroup = $groups->get($fromGroupId);
            $targetGroup = $groups->get($toGroupId);
            if (! $sourceGroup instanceof DiscipleshipGroup) {
                return 'invalid_source_group';
            }
            if (! $targetGroup instanceof DiscipleshipGroup) {
                return 'invalid_target_group';
            }
            if (strtolower(trim((string) $targetGroup->status)) !== 'active') {
                return 'target_group_inactive';
            }

            $sourceStage = normalize_dg_progress_value((string) $sourceGroup->stage);
            $targetStage = normalize_dg_progress_value((string) $targetGroup->stage);
            if ($sourceStage === '' || $targetStage === '' || $sourceStage !== $targetStage) {
                return 'target_group_stage_mismatch';
            }

            $person = Person::query()->whereKey($personId)->lockForUpdate()->first();
            if (! $person instanceof Person
                || (int) $person->branch_id !== $branchId
                || strtolower(trim((string) $person->status)) !== 'active') {
                return 'invalid_person';
            }

            $sourceMembership = DiscipleshipGroupPerson::query()
                ->where('branch_id', $branchId)
                ->where('discipleship_group_id', $fromGroupId)
                ->where('person_id', $personId)
                ->where('role', 'member')
                ->where('status', 'active')
                ->whereNull('ended_on')
                ->lockForUpdate()
                ->first();

            if (! $sourceMembership instanceof DiscipleshipGroupPerson) {
                $activeElsewhere = DiscipleshipGroupPerson::query()
                    ->where('branch_id', $branchId)
                    ->where('person_id', $personId)
                    ->where('status', 'active')
                    ->whereNull('ended_on')
                    ->lockForUpdate()
                    ->first();
                if ($activeElsewhere instanceof DiscipleshipGroupPerson) {
                    return 'member_not_in_source_group';
                }

                $latestMembership = DiscipleshipGroupPerson::query()
                    ->where('branch_id', $branchId)
                    ->where('person_id', $personId)
                    ->where('role', 'member')
                    ->orderByRaw("CASE WHEN status = 'active' AND ended_on IS NULL THEN 1 ELSE 0 END DESC")
                    ->orderByDesc('ended_on')
                    ->orderByDesc('started_on')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (! $latestMembership instanceof DiscipleshipGroupPerson
                    || (int) $latestMembership->discipleship_group_id !== $fromGroupId) {
                    return 'member_not_in_source_group';
                }
            }

            $targetLinks = DiscipleshipGroupPerson::query()
                ->where('branch_id', $branchId)
                ->where('discipleship_group_id', $toGroupId)
                ->where('person_id', $personId)
                ->where('status', 'active')
                ->whereNull('ended_on')
                ->lockForUpdate()
                ->get(['role']);
            if ($targetLinks->contains(fn (DiscipleshipGroupPerson $link): bool => $link->role !== 'member')) {
                return 'leader_cannot_join_own_group';
            }
            if ($targetLinks->contains(fn (DiscipleshipGroupPerson $link): bool => $link->role === 'member')) {
                return 'member_exists';
            }

            $today = today_date();
            if ($sourceMembership instanceof DiscipleshipGroupPerson) {
                $sourceMembership->forceFill([
                    'status' => 'closed',
                    'ended_on' => $today,
                    'end_reason' => 'moved_group',
                ])->save();
            }

            DiscipleshipGroupPerson::query()->create([
                'branch_id' => $branchId,
                'discipleship_group_id' => $toGroupId,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => $targetStage,
                'status' => 'active',
                'started_on' => $today,
                'ended_on' => null,
                'end_reason' => null,
            ]);

            return null;
        });
    }
}
