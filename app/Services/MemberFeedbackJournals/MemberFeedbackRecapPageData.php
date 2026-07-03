<?php

namespace App\Services\MemberFeedbackJournals;

use App\Models\DiscipleshipFeedback;
use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use App\Services\Discipleship\DiscipleshipReadCache;
use App\Support\DiscipleshipPersonProfile;
use DateTimeInterface;
use Illuminate\Http\Request;
use Throwable;

class MemberFeedbackRecapPageData
{
    private const BALANCE_QUESTION_KEYS = [
        'meeting_duration' => 3,
        'meeting_member_count' => 3,
    ];

    public function __construct(
        private readonly DiscipleshipReadCache $cache,
        private readonly MemberFeedbackQuestionCatalog $questionCatalog,
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

        $branchCodes = $this->branchCodes($selectedBranch, $centralReadOnly);
        $data = $this->cache->remember(
            'member-feedback-recap-v3',
            [...$branchCodes, $centralReadOnly ? 'central' : 'branch'],
            fn (): array => $this->build($branchCodes, $centralReadOnly),
        );

        $data['settings'] = ['church_name' => app_church_name()];

        return $data;
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
     * @param  array<int, string>  $branchCodes
     * @return array<string, mixed>
     */
    private function build(array $branchCodes, bool $centralReadOnly): array
    {
        $branchIds = branch_ids_from_slugs($branchCodes);
        $branchLabels = $this->branchLabels();
        $questions = $this->questionMeta();
        $sections = $this->sectionMeta();
        $notes = $this->noteMeta($sections);
        $activeMemberships = $this->activeMemberships($branchIds, $branchLabels);

        $sectionScores = $this->emptySectionScores($sections);
        $questionScores = $this->emptyQuestionScores($questions);
        $coverage = $this->emptyCoverage($activeMemberships['total']);
        $groupAggregates = [];
        $detailRows = [];
        $noteRows = [];
        $totalDirectionalScore = 0.0;
        $totalDirectionalCount = 0;
        $latestSubmittedAt = '';

        foreach ($this->feedbacks($branchIds) as $index => $feedback) {
            $branchCode = branch_slug_from_id($feedback->branch_id);
            $branchLabel = $branchLabels[$branchCode] ?? public_branch_label($branchCode);
            $groupId = (int) ($feedback->discipleship_group_id ?? 0);
            $respondentId = (int) ($feedback->respondent_person_id ?? 0);
            $feedbackSession = (int) ($feedback->feedback_session ?? 0);
            $leaderName = trim((string) ($feedback->leader_name_snapshot ?? '')) ?: '-';
            $groupName = trim((string) ($feedback->group_name_snapshot ?? '')) ?: 'Kelompok';
            $respondentName = trim((string) ($feedback->respondent_name_snapshot ?? '')) ?: '-';
            $groupProgress = normalize_dg_progress_value((string) ($feedback->group_progress_snapshot ?? ''));
            if ($groupProgress === '' && isset($activeMemberships['groups'][$groupId])) {
                $groupProgress = (string) ($activeMemberships['groups'][$groupId]['progress'] ?? '');
            }
            if ($groupProgress === '') {
                $groupProgress = 'DG 1';
            }

            if ($centralReadOnly) {
                $leaderName = $this->appendBranch($leaderName, $branchLabel);
                $groupName = $this->appendBranch($groupName, $branchLabel);
                $respondentName = $this->appendBranch($respondentName, $branchLabel);
            }

            $rowDirectionalScore = 0.0;
            $rowDirectionalCount = 0;
            $rowDisplayScore = 0.0;
            $rowDisplayCount = 0;
            $ratingItems = $this->ratingItems($feedback->ratings);
            $ratingDetailRows = [];

            foreach ($ratingItems as $rating) {
                $questionKey = trim((string) ($rating['question_key'] ?? ''));
                if ($questionKey === '' || ! isset($questions[$questionKey])) {
                    continue;
                }

                $scale = max(1, (int) ($rating['scale'] ?? $questions[$questionKey]['scale']));
                $score = (int) ($rating['score'] ?? 0);
                if ($score < 1 || $score > $scale) {
                    continue;
                }

                $sectionKey = (string) ($questions[$questionKey]['section_key'] ?? 'unknown');
                $isBalance = isset(self::BALANCE_QUESTION_KEYS[$questionKey]);
                $normalized = $isBalance
                    ? $this->balanceScore($score, $scale, self::BALANCE_QUESTION_KEYS[$questionKey])
                    : $this->directionalScore($score, $scale);

                $ratingDetailRows[] = [
                    'section_key' => $sectionKey,
                    'section_label' => (string) ($questions[$questionKey]['section_title'] ?? 'Pertanyaan'),
                    'question_key' => $questionKey,
                    'label' => (string) ($questions[$questionKey]['label'] ?? $questionKey),
                    'score' => $score,
                    'scale' => $scale,
                    'normalized_score' => round($normalized, 1),
                    'type_label' => $isBalance ? 'Keseimbangan' : 'Kepuasan',
                ];
                $rowDisplayScore += $normalized;
                $rowDisplayCount++;
                $sectionScores[$sectionKey]['sum'] += $normalized;
                $sectionScores[$sectionKey]['count']++;
                $questionScores[$questionKey]['sum'] += $normalized;
                $questionScores[$questionKey]['raw_sum'] += $score;
                $questionScores[$questionKey]['count']++;

                if ($isBalance) {
                    $sectionScores[$sectionKey]['balance_sum'] += $normalized;
                    $sectionScores[$sectionKey]['balance_count']++;
                } else {
                    $rowDirectionalScore += $normalized;
                    $rowDirectionalCount++;
                    $sectionScores[$sectionKey]['directional_sum'] += $normalized;
                    $sectionScores[$sectionKey]['directional_count']++;
                    $totalDirectionalScore += $normalized;
                    $totalDirectionalCount++;
                }
            }

            $rowScore = $rowDirectionalCount > 0 ? round($rowDirectionalScore / $rowDirectionalCount, 1) : null;
            $rowDisplayScore = $rowDisplayCount > 0 ? round($rowDisplayScore / $rowDisplayCount, 1) : null;
            $submittedAt = $this->timestampString($feedback->created_at ?? null);
            if ($submittedAt !== '') {
                $latestSubmittedAt = max($latestSubmittedAt, $submittedAt);
            }

            $noteItems = $this->noteItems($feedback->notes);
            $noteSummaryParts = [];
            $noteDetailRows = [];
            foreach ($noteItems as $note) {
                $content = trim((string) ($note['content'] ?? ''));
                if ($content === '') {
                    continue;
                }

                $sectionKey = trim((string) ($note['section_key'] ?? ''));
                $noteKey = trim((string) ($note['note_key'] ?? ''));
                if ($sectionKey === '') {
                    $sectionKey = $this->noteSectionKey($noteKey);
                }
                $noteMeta = $notes[$noteKey] ?? [];
                $sectionLabel = (string) ($noteMeta['section_label'] ?? ($sections[$sectionKey]['title'] ?? 'Catatan'));
                $noteLabel = (string) ($noteMeta['label'] ?? 'Catatan');
                $noteSummaryParts[] = $content;
                $noteDetailRows[] = [
                    'section_key' => $sectionKey,
                    'section_label' => $sectionLabel,
                    'note_key' => $noteKey,
                    'label' => $noteLabel,
                    'content' => $content,
                ];
                $noteRows[] = [
                    'section_key' => $sectionKey,
                    'section_label' => $sectionLabel,
                    'content' => $content,
                    'branch_label' => $branchLabel,
                    'leader_name' => $leaderName,
                    'group_name' => $groupName,
                    'group_progress' => $groupProgress,
                    'feedback_session' => $feedbackSession,
                    'submitted_at' => $submittedAt,
                    'sort_key' => $submittedAt.'-'.$index,
                ];
                if (isset($sectionScores[$sectionKey])) {
                    $sectionScores[$sectionKey]['note_count']++;
                }
            }

            $membershipKey = $this->membershipKey($groupId, $respondentId);
            if ($membershipKey !== '' && isset($activeMemberships['keys'][$membershipKey]) && isset($coverage[$feedbackSession])) {
                $coverage[$feedbackSession]['respondent_keys'][$membershipKey] = true;
            }

            if ($groupId > 0) {
                $groupAggregates[$groupId] ??= [
                    'branch_label' => $branchLabel,
                    'leader_name' => $leaderName,
                    'group_name' => $groupName,
                    'group_progress' => $groupProgress,
                    'session_3_respondent_ids' => [],
                    'session_12_respondent_ids' => [],
                    'respondent_ids' => [],
                    'score_sum' => 0.0,
                    'score_count' => 0,
                    'latest_submitted_at' => '',
                ];
                if ($respondentId > 0) {
                    $groupAggregates[$groupId]['respondent_ids'][$respondentId] = true;
                    if ($feedbackSession === 3) {
                        $groupAggregates[$groupId]['session_3_respondent_ids'][$respondentId] = true;
                    } elseif ($feedbackSession === 12) {
                        $groupAggregates[$groupId]['session_12_respondent_ids'][$respondentId] = true;
                    }
                }
                if ($rowScore !== null) {
                    $groupAggregates[$groupId]['score_sum'] += $rowScore;
                    $groupAggregates[$groupId]['score_count']++;
                }
                $groupAggregates[$groupId]['latest_submitted_at'] = max(
                    (string) $groupAggregates[$groupId]['latest_submitted_at'],
                    $submittedAt,
                );
            }

            $detailRows[] = [
                'id' => (int) ($feedback->id ?? 0),
                'branch_code' => $branchCode,
                'branch_label' => $branchLabel,
                'feedback_session' => $feedbackSession,
                'session_label' => $feedbackSession > 0 ? 'Pertemuan '.$feedbackSession : '-',
                'group_id' => $groupId,
                'leader_name' => $leaderName,
                'group_name' => $groupName,
                'group_progress' => $groupProgress,
                'progress_key' => strtolower(str_replace(' ', '', $groupProgress)),
                'respondent_name' => $respondentName,
                'score' => $rowScore,
                'display_score' => $rowDisplayScore,
                'note_summary' => $this->compactNotes($noteSummaryParts),
                'rating_rows' => $ratingDetailRows,
                'note_rows' => $noteDetailRows,
                'submitted_at' => $submittedAt,
                'search_text' => implode(' ', [
                    $branchLabel,
                    $leaderName,
                    $groupName,
                    $groupProgress,
                    $respondentName,
                    implode(' ', $noteSummaryParts),
                ]),
            ];
        }

        usort($detailRows, static fn (array $a, array $b): int => strcmp((string) ($b['submitted_at'] ?? ''), (string) ($a['submitted_at'] ?? '')));
        usort($noteRows, static fn (array $a, array $b): int => strcmp((string) ($b['sort_key'] ?? ''), (string) ($a['sort_key'] ?? '')));

        $groupRows = $this->groupRows($activeMemberships['groups'], $groupAggregates);
        $sectionRows = $this->sectionRows($sectionScores);
        $questionRows = $this->questionRows($questionScores);
        $coverageRows = $this->coverageRows($coverage);
        $totalJournals = count($detailRows);
        $feedbackGroupCount = count(array_filter(
            $groupRows,
            static fn (array $row): bool => ((int) ($row['session_3_count'] ?? 0) + (int) ($row['session_12_count'] ?? 0)) > 0,
        ));
        $overallScore = $totalDirectionalCount > 0 ? round($totalDirectionalScore / $totalDirectionalCount, 1) : 0.0;
        $submittedCoverageTotal = array_sum(array_map(
            static fn (array $row): int => (int) ($row['submitted'] ?? 0),
            $coverageRows,
        ));

        return [
            'page' => 'member_feedback_recap',
            'summary' => [
                'total_journals' => $totalJournals,
                'overall_score' => $overallScore,
                'feedback_group_count' => $feedbackGroupCount,
                'active_member_count' => $activeMemberships['total'],
                'coverage_percent' => $activeMemberships['total'] > 0
                    ? round($submittedCoverageTotal / ($activeMemberships['total'] * 2) * 100)
                    : 0,
                'latest_submitted_at' => $latestSubmittedAt,
            ],
            'section_scores' => $sectionRows,
            'question_scores' => $questionRows,
            'coverage' => $coverageRows,
            'group_rows' => $groupRows,
            'note_rows' => array_slice($noteRows, 0, 18),
            'detail_rows' => $detailRows,
            'filters' => [
                'sessions' => ['all' => 'Semua Pertemuan', '3' => 'Pertemuan 3', '12' => 'Pertemuan 12'],
                'progress' => ['all' => 'Semua Progress', 'dg1' => 'DG 1', 'dg2' => 'DG 2', 'dg3' => 'DG 3'],
            ],
        ];
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

            $labels[$branchCode] = trim((string) ($option['label'] ?? strtoupper($branchCode))) ?: strtoupper($branchCode);
        }

        return $labels;
    }

    /**
     * @return array<string, array{title:string}>
     */
    private function sectionMeta(): array
    {
        $sections = [];
        foreach ($this->questionCatalog->sections() as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            $title = trim((string) ($section['title'] ?? $sectionKey));
            $sections[(string) $sectionKey] = ['title' => $title !== '' ? $title : (string) $sectionKey];
        }

        return $sections;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function questionMeta(): array
    {
        $questions = [];
        foreach ($this->questionCatalog->sections() as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach (($section['ratings'] ?? []) as $rating) {
                if (! is_array($rating)) {
                    continue;
                }

                $key = trim((string) ($rating['key'] ?? ''));
                if ($key === '') {
                    continue;
                }

                $questions[$key] = [
                    'section_key' => (string) $sectionKey,
                    'section_title' => trim((string) ($section['title'] ?? $sectionKey)),
                    'label' => trim((string) ($rating['label'] ?? $key)),
                    'scale' => max(1, (int) ($rating['scale'] ?? 10)),
                    'is_balance' => isset(self::BALANCE_QUESTION_KEYS[$key]),
                    'ideal' => self::BALANCE_QUESTION_KEYS[$key] ?? null,
                ];
            }
        }

        return $questions;
    }

    /**
     * @param  array<string, array{title:string}>  $sections
     * @return array<string, array<string, string>>
     */
    private function noteMeta(array $sections): array
    {
        $notes = [];
        foreach ($this->questionCatalog->sections() as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = trim((string) ($section['note_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $notes[$key] = [
                'section_key' => (string) $sectionKey,
                'section_label' => (string) ($sections[$sectionKey]['title'] ?? ($section['title'] ?? $sectionKey)),
                'label' => trim((string) ($section['note_label'] ?? 'Catatan')) ?: 'Catatan',
            ];
        }

        return $notes;
    }

    /**
     * @param  array<string, array{title:string}>  $sections
     * @return array<string, array<string, mixed>>
     */
    private function emptySectionScores(array $sections): array
    {
        $rows = [];
        foreach ($sections as $sectionKey => $section) {
            $rows[$sectionKey] = [
                'section_key' => $sectionKey,
                'label' => $section['title'],
                'sum' => 0.0,
                'count' => 0,
                'directional_sum' => 0.0,
                'directional_count' => 0,
                'balance_sum' => 0.0,
                'balance_count' => 0,
                'note_count' => 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $questions
     * @return array<string, array<string, mixed>>
     */
    private function emptyQuestionScores(array $questions): array
    {
        $rows = [];
        foreach ($questions as $questionKey => $question) {
            $rows[$questionKey] = [
                'question_key' => $questionKey,
                'label' => $question['label'],
                'section_key' => $question['section_key'],
                'section_label' => $question['section_title'],
                'is_balance' => (bool) ($question['is_balance'] ?? false),
                'sum' => 0.0,
                'raw_sum' => 0.0,
                'count' => 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $branchIds
     * @param  array<string, string>  $branchLabels
     * @return array{total:int,keys:array<string, bool>,groups:array<int, array<string, mixed>>}
     */
    private function activeMemberships(array $branchIds, array $branchLabels): array
    {
        if ($branchIds === []) {
            return ['total' => 0, 'keys' => [], 'groups' => []];
        }

        try {
            $groups = DiscipleshipGroup::query()
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'active')
                ->orderBy('id')
                ->get(['id', 'branch_id', 'name', 'status', 'start_stage', 'current_stage']);
        } catch (Throwable) {
            return ['total' => 0, 'keys' => [], 'groups' => []];
        }

        $groupRows = [];
        foreach ($groups as $group) {
            $groupId = (int) $group->getKey();
            $branchCode = branch_slug_from_id($group->branch_id);
            $progress = normalize_dg_progress_value((string) ($group->current_stage ?? $group->start_stage ?? '')) ?: 'DG 1';
            $groupRows[$groupId] = [
                'id' => $groupId,
                'branch_label' => $branchLabels[$branchCode] ?? public_branch_label($branchCode),
                'leader_name' => '-',
                'name' => trim((string) ($group->name ?? 'Kelompok')) ?: 'Kelompok',
                'progress' => $progress,
                'active_member_count' => 0,
                'member_keys' => [],
            ];
        }

        if ($groupRows === []) {
            return ['total' => 0, 'keys' => [], 'groups' => []];
        }

        $keys = [];
        try {
            $memberships = DiscipleshipGroupPerson::query()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('discipleship_group_id', array_keys($groupRows))
                ->where('status', 'active')
                ->orderBy('id')
                ->get(['discipleship_group_id', 'person_id', 'role']);
        } catch (Throwable) {
            return ['total' => 0, 'keys' => [], 'groups' => $groupRows];
        }

        $personIds = $memberships
            ->pluck('person_id')
            ->filter(static fn ($personId): bool => (int) $personId > 0)
            ->map(static fn ($personId): int => (int) $personId)
            ->unique()
            ->values()
            ->all();
        $personNames = [];
        if ($personIds !== []) {
            try {
                $personNames = DiscipleshipPersonProfile::namesByPersonIds($personIds);
            } catch (Throwable) {
                $personNames = [];
            }
        }

        foreach ($memberships as $membership) {
            $groupId = (int) $membership->discipleship_group_id;
            $personId = (int) $membership->person_id;
            $role = strtolower(trim((string) ($membership->role ?? '')));
            if (! isset($groupRows[$groupId]) || $personId <= 0) {
                continue;
            }

            if ($role === 'member') {
                $key = $this->membershipKey($groupId, $personId);
                if ($key === '') {
                    continue;
                }

                $keys[$key] = true;
                $groupRows[$groupId]['member_keys'][$key] = true;
                $groupRows[$groupId]['active_member_count'] = count($groupRows[$groupId]['member_keys']);
                continue;
            }

            if ((string) ($groupRows[$groupId]['leader_name'] ?? '-') === '-') {
                $leaderName = trim((string) ($personNames[$personId] ?? ''));
                if ($leaderName !== '') {
                    $groupRows[$groupId]['leader_name'] = $leaderName;
                }
            }
        }

        foreach ($groupRows as &$groupRow) {
            unset($groupRow['member_keys']);
        }
        unset($groupRow);

        return ['total' => count($keys), 'keys' => $keys, 'groups' => $groupRows];
    }

    /**
     * @return iterable<int, DiscipleshipFeedback>
     */
    private function feedbacks(array $branchIds): iterable
    {
        if ($branchIds === []) {
            return [];
        }

        try {
            return DiscipleshipFeedback::query()
                ->select([
                    'id', 'branch_id', 'feedback_session', 'discipleship_group_id', 'leader_person_id',
                    'respondent_person_id', 'respondent_name_snapshot', 'leader_name_snapshot',
                    'group_name_snapshot', 'group_label_snapshot', 'group_progress_snapshot',
                    'ratings', 'notes', 'source', 'created_at', 'updated_at',
                ])
                ->whereIn('branch_id', $branchIds)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  mixed  $value
     * @return array<int, array<string, mixed>>
     */
    private function ratingItems(mixed $value): array
    {
        return $this->jsonItems($value);
    }

    /**
     * @param  mixed  $value
     * @return array<int, array<string, mixed>>
     */
    private function noteItems(mixed $value): array
    {
        return $this->jsonItems($value);
    }

    /**
     * @param  mixed  $value
     * @return array<int, array<string, mixed>>
     */
    private function jsonItems(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_array'));
            }
        }

        return [];
    }

    private function directionalScore(int $score, int $scale): float
    {
        return round(($score / max(1, $scale)) * 10, 2);
    }

    private function balanceScore(int $score, int $scale, int $ideal): float
    {
        $distance = abs($score - $ideal);
        $maxDistance = max(abs(1 - $ideal), abs($scale - $ideal), 1);

        return round(max(0, 10 - (($distance / $maxDistance) * 10)), 2);
    }

    /**
     * @param  array<int, string>  $notes
     */
    private function compactNotes(array $notes): string
    {
        $text = trim(implode(' ', array_map(static fn (string $note): string => trim($note), $notes)));
        if ($text === '') {
            return '-';
        }

        return mb_strlen($text) > 140 ? mb_substr($text, 0, 137).'...' : $text;
    }

    private function noteSectionKey(string $noteKey): string
    {
        foreach ($this->questionCatalog->noteQuestions() as $note) {
            if (($note['key'] ?? '') === $noteKey) {
                return (string) ($note['section_key'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $activeGroups
     * @param  array<int, array<string, mixed>>  $groupAggregates
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(array $activeGroups, array $groupAggregates): array
    {
        $rows = [];
        foreach ($activeGroups as $groupId => $group) {
            $row = $groupAggregates[$groupId] ?? [];
            $scoreCount = (int) ($row['score_count'] ?? 0);
            $session3Count = count($row['session_3_respondent_ids'] ?? []);
            $session12Count = count($row['session_12_respondent_ids'] ?? []);
            $rows[] = [
                'group_id' => $groupId,
                'branch_label' => $group['branch_label'] ?? ($row['branch_label'] ?? '-'),
                'leader_name' => $group['leader_name'] ?? ($row['leader_name'] ?? '-'),
                'group_name' => $group['name'] ?? ($row['group_name'] ?? 'Kelompok'),
                'group_progress' => $group['progress'] ?? ($row['group_progress'] ?? 'DG 1'),
                'active_member_count' => (int) ($group['active_member_count'] ?? 0),
                'session_3_count' => $session3Count,
                'session_12_count' => $session12Count,
                'respondent_count' => count($row['respondent_ids'] ?? []),
                'score' => $scoreCount > 0 ? round(((float) $row['score_sum']) / $scoreCount, 1) : 0.0,
                'latest_submitted_at' => (string) ($row['latest_submitted_at'] ?? ''),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            foreach (['branch_label', 'group_progress', 'leader_name', 'group_name'] as $key) {
                $compare = strcmp((string) ($a[$key] ?? ''), (string) ($b[$key] ?? ''));
                if ($compare !== 0) {
                    return $compare;
                }
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sectionScores
     * @return array<int, array<string, mixed>>
     */
    private function sectionRows(array $sectionScores): array
    {
        $rows = [];
        foreach ($sectionScores as $row) {
            $count = (int) ($row['count'] ?? 0);
            $directionalCount = (int) ($row['directional_count'] ?? 0);
            $balanceCount = (int) ($row['balance_count'] ?? 0);
            $rows[] = [
                'section_key' => $row['section_key'],
                'label' => $row['label'],
                'score' => $count > 0 ? round(((float) $row['sum']) / $count, 1) : 0.0,
                'directional_score' => $directionalCount > 0 ? round(((float) $row['directional_sum']) / $directionalCount, 1) : null,
                'balance_score' => $balanceCount > 0 ? round(((float) $row['balance_sum']) / $balanceCount, 1) : null,
                'rating_count' => $count,
                'note_count' => (int) ($row['note_count'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $questionScores
     * @return array<int, array<string, mixed>>
     */
    private function questionRows(array $questionScores): array
    {
        $rows = [];
        foreach ($questionScores as $row) {
            $count = (int) ($row['count'] ?? 0);
            if ($count === 0) {
                continue;
            }

            $rows[] = [
                'question_key' => $row['question_key'],
                'label' => $row['label'],
                'section_key' => $row['section_key'],
                'section_label' => $row['section_label'],
                'is_balance' => (bool) ($row['is_balance'] ?? false),
                'score' => round(((float) $row['sum']) / $count, 1),
                'raw_average' => round(((float) $row['raw_sum']) / $count, 1),
                'count' => $count,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $scoreCompare = ((float) ($a['score'] ?? 0)) <=> ((float) ($b['score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<int, array{submitted:int,total:int,respondent_keys:array<string, bool>}>
     */
    private function emptyCoverage(int $activeMemberCount): array
    {
        return [
            3 => ['submitted' => 0, 'total' => $activeMemberCount, 'respondent_keys' => []],
            12 => ['submitted' => 0, 'total' => $activeMemberCount, 'respondent_keys' => []],
        ];
    }

    /**
     * @param  array<int, array{submitted:int,total:int,respondent_keys:array<string, bool>}>  $coverage
     * @return array<int, array<string, mixed>>
     */
    private function coverageRows(array $coverage): array
    {
        $rows = [];
        foreach ([3, 12] as $session) {
            $submitted = count($coverage[$session]['respondent_keys'] ?? []);
            $total = (int) ($coverage[$session]['total'] ?? 0);
            $rows[] = [
                'session' => $session,
                'label' => 'Pertemuan '.$session,
                'submitted' => $submitted,
                'total' => $total,
                'percent' => $total > 0 ? round(($submitted / $total) * 100) : 0,
            ];
        }

        return $rows;
    }

    private function membershipKey(int $groupId, int $personId): string
    {
        return $groupId > 0 && $personId > 0 ? $groupId.':'.$personId : '';
    }

    private function appendBranch(string $name, string $branchLabel): string
    {
        if ($name === '-' || $branchLabel === '') {
            return $name;
        }

        return append_branch_suffix($name, $branchLabel);
    }

    private function timestampString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }
}
