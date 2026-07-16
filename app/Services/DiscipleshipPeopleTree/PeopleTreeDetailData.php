<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\MskParticipants\MskParticipantHistoryData;
use App\Services\MskParticipants\MskParticipantProfileData;
use Illuminate\Database\Eloquent\Builder;

class PeopleTreeDetailData
{
    public function __construct(
        private readonly CurrentDiscipleshipScope $scope,
        private readonly PeopleTreeModelStore $modelStore,
        private readonly MskParticipantHistoryData $historyData,
        private readonly MskParticipantProfileData $profileData,
    ) {}

    /** @return array{title:string,html:string,edit_url:?string,edit:array<string,mixed>}|null */
    public function person(int $personId): ?array
    {
        $branchIds = $this->scope->branchIds();
        if ($personId < 1 || $branchIds === []) {
            return null;
        }

        $person = Person::query()
            ->select(Person::VIEW_COLUMNS)
            ->whereKey($personId)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($branchIds): void {
                $query->whereExists(function ($membership) use ($branchIds): void {
                    $membership->selectRaw('1')
                        ->from('keanggotaan_kelompok_dg as tree_membership')
                        ->whereColumn('tree_membership.person_id', 'orang.id')
                        ->whereIn('tree_membership.branch_id', $branchIds);
                })->orWhereExists(function ($manual) use ($branchIds): void {
                    $manual->selectRaw('1')
                        ->from('dg_manual as tree_manual')
                        ->whereColumn('tree_manual.person_id', 'orang.id')
                        ->whereIn('tree_manual.branch_id', $branchIds);
                })->orWhereExists(function ($group) use ($branchIds): void {
                    $group->selectRaw('1')
                        ->from('kelompok_dg as initiated_group')
                        ->whereColumn('initiated_group.initiated_by_person_id', 'orang.id')
                        ->whereIn('initiated_group.branch_id', $branchIds);
                });
            })
            ->first();
        if (! $person instanceof Person) {
            return null;
        }

        $row = $person->toViewArray();
        $personBranchId = (int) $person->branch_id;
        $branchCode = normalize_user_branch((string) $person->branch_code);
        $selectedBranchIds = array_map('intval', $branchIds);
        if ($this->scope->isReadOnly() || ! in_array($personBranchId, $selectedBranchIds, true)) {
            $branchLabel = $this->modelStore->branchLabels()[$branchCode] ?? strtoupper($branchCode);
            $row['full_name'] = append_branch_suffix((string) ($row['full_name'] ?? ''), $branchLabel);
        }

        $id = (string) $person->getKey();
        $history = $this->historyData->forKnownParticipant($row, $branchIds, $personBranchId);
        $profiles = $this->profileData->forParticipants([$row], [$id => $history]);
        $profile = is_array($profiles[$id] ?? null) ? $profiles[$id] : [];
        $profile['subtitle'] = 'Anggota Pohon Pemuridan';
        if (($profile['batch'] ?? '-') === '-') {
            $profile['batch_badge_label'] = 'Pohon Pemuridan';
        }
        if (($profile['status_label'] ?? '') === 'Belum' && (int) ($profile['session_count'] ?? 0) === 0) {
            $profile['status_label'] = 'Aktif';
            $profile['status_class'] = 'is-progress';
        }

        $title = trim((string) ($profile['full_name'] ?? $row['full_name'] ?? 'Profil Orang')) ?: 'Profil Orang';

        return [
            'title' => $title,
            'html' => view('discipleship.msk-participants.profile', ['profile' => $profile])->render(),
            'edit_url' => $this->scope->isReadOnly()
                ? null
                : route('discipleship.tree.people.save', $this->branchRouteParams()),
            'edit' => [
                'person_id' => $id,
                'member_id' => $id,
                'name' => $title,
                'phone' => trim((string) ($row['whatsapp'] ?? '')),
                'notes' => (string) ($row['notes'] ?? ''),
            ],
        ];
    }

    /** @return array{title:string,html:string,edit_url:?string}|null */
    public function group(int $groupId): ?array
    {
        $branchIds = $this->scope->branchIds();
        $model = $this->modelStore->detailModelForGroup($groupId, $branchIds);
        if ($model === null) {
            return null;
        }

        $branchCodes = array_values(array_filter(array_map(
            static fn (int $branchId): string => normalize_user_branch(branch_slug_from_id($branchId)),
            $branchIds,
        )));
        $people = $this->modelStore->peopleForModel($model, [], [], $this->scope->isReadOnly());
        $reports = $this->modelStore->meetingReportsForBranches(
            $branchCodes,
            $this->scope->isReadOnly(),
            $groupId,
        );
        $views = build_people_tree_group_history_views($model, index_by_id($people), $reports);
        $detail = $views[(string) $groupId] ?? null;
        if (! is_array($detail)) {
            return null;
        }

        return [
            'title' => trim((string) ($detail['title'] ?? 'Riwayat Kelompok')) ?: 'Riwayat Kelompok',
            'html' => (string) ($detail['content'] ?? ''),
            'edit_url' => $this->scope->isReadOnly()
                ? null
                : route('discipleship.tree.groups.save', $this->branchRouteParams()),
        ];
    }

    /** @return array<string, int> */
    private function branchRouteParams(): array
    {
        $branchId = $this->scope->selectedBranchId();

        return $branchId !== null ? ['branch_id' => $branchId] : [];
    }
}
