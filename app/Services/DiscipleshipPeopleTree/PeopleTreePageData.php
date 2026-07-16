<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Services\Discipleship\DiscipleshipReadCache;
use Illuminate\Http\Request;

class PeopleTreePageData
{
    public function __construct(
        private readonly PeopleTreeModelStore $modelStore,
        private readonly DiscipleshipReadCache $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCurrentContext(Request $request): array
    {
        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedBranch = $centralReadOnly
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_user_branch(current_user_branch());

        $branchCodes = $this->modelStore->branchCodesForSelection($selectedBranch, $centralReadOnly);
        $data = $this->cache->remember('people-tree', [...$branchCodes, $centralReadOnly ? 'central' : 'branch'], function () use ($branchCodes, $centralReadOnly, $selectedBranch): array {
            $members = [];
            // Only the compact candidate rows needed by the add-member form belong
            // in the initial response. Full profiles and histories are fetched for
            // a single visible person when their node is opened.
            $mskClasses = $centralReadOnly
                ? []
                : $this->modelStore->completedParticipantCandidatesForBranches($branchCodes);
            $discipleshipV2Model = $this->modelStore->modelForContext($branchCodes, $centralReadOnly);
            $people = $this->modelStore->peopleForModel($discipleshipV2Model, $members, $mskClasses, $centralReadOnly);
            $currentBranchCode = normalize_user_branch(current_user_branch());

            return [
                'page' => 'people_tree',
                'centralReadOnly' => $centralReadOnly,
                'centralSelectedBranch' => $selectedBranch,
                'members' => $members,
                'mskClasses' => $mskClasses,
                'people' => $people,
                'leaderCandidates' => $centralReadOnly ? [] : $this->modelStore->leaderCandidatesForBranch($currentBranchCode),
                'groups' => $this->modelStore->groupsForModel($discipleshipV2Model, $people, $centralReadOnly),
                'discipleshipV2Enabled' => true,
                'discipleshipV2Branch' => normalize_user_branch(current_user_branch()),
                'discipleshipV2Model' => $discipleshipV2Model,
                'progressOptions' => ['DG 1', 'DG 2', 'DG 3'],
                'rootLeaderName' => 'Injil',
                'rootLeaderId' => 'virtual_injil',
            ];
        });
        $data['settings'] = ['church_name' => app_church_name()];
        $branchRouteParams = current_user_branch_id() !== null
            ? ['branch_id' => current_user_branch_id()]
            : [];
        $data['peopleTreeUrls'] = [
            'save_person' => route('discipleship.tree.people.save', $branchRouteParams),
            'delete_person' => route('discipleship.tree.people.delete', $branchRouteParams),
            'save_group' => route('discipleship.tree.groups.save', $branchRouteParams),
            'leave_person_group' => route('discipleship.tree.groups.leave', $branchRouteParams),
            'complete_group' => route('discipleship.tree.groups.complete', $branchRouteParams),
            'reactivate_group' => route('discipleship.tree.groups.reactivate', $branchRouteParams),
            'export_dot' => route('discipleship.tree.export-dot', $branchRouteParams),
            'person_detail' => route('discipleship.tree.people.detail', ['person' => '__id__'] + $branchRouteParams),
            'group_detail' => route('discipleship.tree.groups.detail', ['group' => '__id__'] + $branchRouteParams),
        ];

        return $data;
    }
}
