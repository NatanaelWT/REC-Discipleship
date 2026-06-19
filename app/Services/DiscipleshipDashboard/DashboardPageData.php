<?php

namespace App\Services\DiscipleshipDashboard;

use App\Services\DiscipleshipPeopleTree\PeopleTreeModelStore;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use Illuminate\Http\Request;

class DashboardPageData
{
    public function __construct(
        private readonly PeopleTreeModelStore $modelStore,
        private readonly DiscipleshipTargetReader $targetReader,
    ) {
    }

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
        $members = [];
        $mskClasses = $this->modelStore->participantsForBranches($branchCodes, $centralReadOnly);
        $discipleshipV2Model = $this->modelStore->modelForContext($branchCodes, $centralReadOnly);
        $people = $this->modelStore->peopleForModel($discipleshipV2Model, $members, $mskClasses, $centralReadOnly);
        $groups = $this->modelStore->groupsForModel($discipleshipV2Model, $people, $centralReadOnly);

        return [
            'settings' => ['church_name' => app_church_name()],
            'page' => 'discipleship_dashboard',
            'centralReadOnly' => $centralReadOnly,
            'centralSelectedBranch' => $selectedBranch,
            'members' => $members,
            'mskClasses' => $mskClasses,
            'people' => $people,
            'groups' => $groups,
            'dgMeetingReports' => $this->modelStore->meetingReportsForBranches($branchCodes, $centralReadOnly),
            'discipleshipTargets' => $this->targetReader->formValuesForBranch($this->targetBranch($selectedBranch)),
            'discipleshipV2Enabled' => true,
            'discipleshipV2Branch' => normalize_public_branch_code(current_user_branch()),
            'discipleshipV2Model' => $discipleshipV2Model,
            'progressOptions' => ['DG 1', 'DG 2', 'DG 3'],
            'rootLeaderName' => 'Injil',
            'rootLeaderId' => 'virtual_injil',
        ];
    }

    private function targetBranch(string $selectedBranch): string
    {
        if ($selectedBranch !== '' && $selectedBranch !== 'all') {
            return $selectedBranch;
        }

        return current_user_branch();
    }
}
