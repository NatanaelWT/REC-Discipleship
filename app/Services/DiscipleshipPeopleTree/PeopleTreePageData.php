<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Services\Discipleship\DiscipleshipReadCache;
use App\Services\MskParticipants\MskParticipantHistoryData;
use App\Services\MskParticipants\MskParticipantProfileData;
use Illuminate\Http\Request;

class PeopleTreePageData
{
    public function __construct(
        private readonly PeopleTreeModelStore $modelStore,
        private readonly DiscipleshipReadCache $cache,
        private readonly MskParticipantHistoryData $historyData,
        private readonly MskParticipantProfileData $profileData,
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
            $branchIds = branch_ids_from_slugs($branchCodes);
            $treeProfileRows = $this->treeProfileRows($people, $mskClasses);
            $treeProfileHistories = $this->historyData->forParticipants($treeProfileRows, $branchIds);
            $treePersonProfiles = $this->profileData->forParticipants($treeProfileRows, $treeProfileHistories);
            foreach ($treePersonProfiles as &$profile) {
                $profile['subtitle'] = 'Anggota Pohon Pemuridan';
                if (($profile['batch'] ?? '-') === '-') {
                    $profile['batch_badge_label'] = 'Pohon Pemuridan';
                }
                if (($profile['status_label'] ?? '') === 'Belum' && (int) ($profile['session_count'] ?? 0) === 0) {
                    $profile['status_label'] = 'Aktif';
                    $profile['status_class'] = 'is-progress';
                }
            }
            unset($profile);

            return [
                'page' => 'people_tree',
                'centralReadOnly' => $centralReadOnly,
                'centralSelectedBranch' => $selectedBranch,
                'members' => $members,
                'mskClasses' => $mskClasses,
                'people' => $people,
                'treePersonProfiles' => $treePersonProfiles,
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

    /**
     * @param  array<int, array<string, mixed>>  $people
     * @param  array<int, array<string, mixed>>  $mskClasses
     * @return array<int, array<string, mixed>>
     */
    private function treeProfileRows(array $people, array $mskClasses): array
    {
        $participantsByPersonId = [];
        foreach ($mskClasses as $participant) {
            $personId = trim((string) ($participant['member_id'] ?? ''));
            if ($personId !== '' && ! isset($participantsByPersonId[$personId])) {
                $participantsByPersonId[$personId] = $participant;
            }
        }

        $rows = [];
        foreach ($people as $person) {
            $personId = trim((string) ($person['id'] ?? ''));
            if ($personId === '' || $personId === 'virtual_injil') {
                continue;
            }

            $row = is_array($participantsByPersonId[$personId] ?? null)
                ? $participantsByPersonId[$personId]
                : [
                    'full_name' => trim((string) ($person['name'] ?? '')) ?: '-',
                    'gender' => (string) ($person['gender'] ?? ''),
                    'whatsapp' => (string) ($person['phone'] ?? ''),
                    'notes' => (string) ($person['notes'] ?? ''),
                    'msk_month' => '',
                    'session_numbers' => [],
                    'status' => 'active',
                ];

            $row['id'] = $personId;
            $row['member_id'] = $personId;
            $row['full_name'] = trim((string) ($row['full_name'] ?? '')) ?: (trim((string) ($person['name'] ?? '')) ?: '-');
            if (trim((string) ($row['whatsapp'] ?? '')) === '') {
                $row['whatsapp'] = (string) ($person['phone'] ?? '');
            }
            if (trim((string) ($row['notes'] ?? '')) === '') {
                $row['notes'] = (string) ($person['notes'] ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
