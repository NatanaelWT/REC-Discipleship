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
            : normalize_public_branch_code(current_user_branch());

        $branchCodes = $this->modelStore->branchCodesForSelection($selectedBranch, $centralReadOnly);
        $data = $this->cache->remember('people-tree', [...$branchCodes, $centralReadOnly ? 'central' : 'branch'], function () use ($branchCodes, $centralReadOnly, $selectedBranch): array {
            $members = [];
            $mskClasses = $this->modelStore->participantsForBranches($branchCodes, $centralReadOnly);
            $discipleshipV2Model = $this->modelStore->modelForContext($branchCodes, $centralReadOnly);
            $people = $this->modelStore->peopleForModel($discipleshipV2Model, $members, $mskClasses, $centralReadOnly);

            return [
                'page' => 'people_tree',
                'centralReadOnly' => $centralReadOnly,
                'centralSelectedBranch' => $selectedBranch,
                'members' => $members,
                'mskClasses' => $mskClasses,
                'people' => $people,
                'groups' => $this->modelStore->groupsForModel($discipleshipV2Model, $people, $centralReadOnly),
                'dgMeetingReports' => $this->modelStore->meetingReportsForBranches($branchCodes, $centralReadOnly),
                'discipleshipV2Enabled' => true,
                'discipleshipV2Branch' => normalize_public_branch_code(current_user_branch()),
                'discipleshipV2Model' => $discipleshipV2Model,
                'progressOptions' => ['DG 1', 'DG 2', 'DG 3'],
                'rootLeaderName' => 'Injil',
                'rootLeaderId' => 'virtual_injil',
            ];
        });
        $data['settings'] = ['church_name' => app_church_name()];
        $data['peopleTreeUrls'] = [
            'save_person' => route('discipleship.tree.people.save'),
            'delete_person' => route('discipleship.tree.people.delete'),
            'save_group' => route('discipleship.tree.groups.save'),
            'leave_person_group' => route('discipleship.tree.groups.leave'),
            'complete_group' => route('discipleship.tree.groups.complete'),
            'reactivate_group' => route('discipleship.tree.groups.reactivate'),
            'export_dot' => route('discipleship.tree.export-dot'),
        ];

        return $data;
    }
}
