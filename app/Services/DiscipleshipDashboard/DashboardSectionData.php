<?php

namespace App\Services\DiscipleshipDashboard;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipPerson;
use App\Models\MskParticipant;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Support\DiscipleshipPersonProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardSectionData
{
    public function __construct(
        private readonly CurrentDiscipleshipScope $scope,
        private readonly DiscipleshipDashboardSummaryQuery $summary,
    ) {}

    /** @return array<string, mixed> */
    public function incompleteMsk(Request $request): array
    {
        $sessions = DB::getDriverName() === 'sqlite'
            ? "json_array_length(COALESCE(session_numbers, '[]'))"
            : "JSON_LENGTH(COALESCE(session_numbers, '[]'))";

        $participants = MskParticipant::query()
            ->select(['id', 'branch_id', 'full_name', 'whatsapp', 'batch_month', 'session_numbers'])
            ->whereIn('branch_id', $this->scope->branchIds())
            ->where('status', 'active')
            ->whereRaw($sessions.' < 12')
            ->orderBy('branch_id')
            ->orderBy('batch_month')
            ->orderBy('full_name')
            ->get();

        $branches = $this->scope->optionsById();
        $participants = $participants->map(static function (MskParticipant $participant) use ($branches): array {
            $sessions = normalize_msk_session_numbers($participant->session_numbers ?? []);
            $phone = trim((string) $participant->whatsapp);
            $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
            if (str_starts_with($phoneDigits, '0')) {
                $phoneDigits = '62'.substr($phoneDigits, 1);
            } elseif (str_starts_with($phoneDigits, '8')) {
                $phoneDigits = '62'.$phoneDigits;
            }

            return [
                'id' => (int) $participant->id,
                'name' => trim((string) $participant->full_name) ?: '-',
                'phone' => $phone !== '' ? $phone : '-',
                'whatsapp_url' => $phoneDigits !== '' ? 'https://wa.me/'.$phoneDigits : '',
                'batch_month' => trim((string) $participant->batch_month),
                'session_numbers' => $sessions,
                'session_count' => count($sessions),
                'branch_label' => $branches[(int) $participant->branch_id]['label'] ?? 'Tanpa cabang',
            ];
        });

        return [
            'participants' => $participants,
            'centralReadOnly' => $this->scope->isReadOnly(),
        ];
    }

    /** @return array<string, mixed> */
    public function overdueGroups(Request $request): array
    {
        $latest = DB::table('discipleship_meeting_reports')
            ->selectRaw('branch_id, discipleship_group_id, MAX(meeting_date) AS last_report_date')
            ->whereNotNull('discipleship_group_id')
            ->groupBy('branch_id', 'discipleship_group_id');

        $groups = DiscipleshipGroup::query()
            ->from('discipleship_groups as g')
            ->leftJoinSub($latest, 'latest_report', function ($join): void {
                $join->on('latest_report.branch_id', '=', 'g.branch_id')
                    ->on('latest_report.discipleship_group_id', '=', 'g.id');
            })
            ->whereIn('g.branch_id', $this->scope->branchIds())
            ->where('g.status', 'active')
            ->where(function ($query): void {
                $query->whereNull('latest_report.last_report_date')
                    ->orWhere('latest_report.last_report_date', '<', now()->subDays(30)->toDateString());
            })
            ->select(['g.id', 'g.branch_id', 'g.name', 'g.current_stage', 'latest_report.last_report_date'])
            ->orderByRaw('CASE WHEN latest_report.last_report_date IS NULL THEN 0 ELSE 1 END')
            ->orderBy('latest_report.last_report_date')
            ->orderBy('g.name')
            ->get();

        $groupIds = $groups->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $peopleByGroup = $this->peopleByGroup($groupIds);
        $branches = $this->scope->optionsById();

        $groups = $groups->map(static function (DiscipleshipGroup $group) use ($peopleByGroup, $branches): array {
            $people = $peopleByGroup[(int) $group->id] ?? ['leaders' => [], 'members' => []];

            return [
                'id' => (int) $group->id,
                'leader_name' => $people['leaders'][0] ?? '-',
                'members_first_names' => implode(', ', array_slice($people['members'], 0, 8)) ?: '-',
                'progress' => normalize_dg_progress_value((string) $group->current_stage) ?: 'DG 1',
                'last_report_date' => trim((string) $group->last_report_date),
                'branch_label' => $branches[(int) $group->branch_id]['label'] ?? 'Tanpa cabang',
            ];
        });

        return ['groups' => $groups];
    }

    /** @return array<string, mixed> */
    public function branchBreakdown(): array
    {
        return ['branches' => $this->summary->get()['branchSummaryRows'] ?? []];
    }

    /** @param array<int, int> $groupIds */
    private function peopleByGroup(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $links = DiscipleshipGroupPerson::query()
            ->whereIn('discipleship_group_id', $groupIds)
            ->where('status', 'active')
            ->whereNull('ended_on')
            ->get(['discipleship_group_id', 'person_id', 'role']);
        $personIds = $links->pluck('person_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $names = DiscipleshipPersonProfile::namesByPersonIds($personIds);

        $result = [];
        foreach ($links as $link) {
            $groupId = (int) $link->discipleship_group_id;
            $name = $names[(int) $link->person_id] ?? '';
            if ($name === '') {
                continue;
            }
            $bucket = strtolower((string) $link->role) === 'member' ? 'members' : 'leaders';
            $label = $bucket === 'members' ? (preg_split('/\s+/', $name)[0] ?? $name) : $name;
            $result[$groupId][$bucket][] = $label;
            $result[$groupId][$bucket] = array_values(array_unique($result[$groupId][$bucket]));
        }

        return $result;
    }
}
