<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MigrateMemberFeedbackJournalsToLaravelTables extends Command
{
    protected $signature = 'rec:migrate-member-feedback-journals {--dry-run : Count rows without writing}';

    protected $description = 'Migrate DG member feedback journals into normalized Laravel tables.';

    public function handle(): int
    {
        foreach ([
            'rec_dg_member_feedback_journals',
            'discipleship_member_feedback_journals',
            'discipleship_member_feedback_ratings',
            'discipleship_member_feedback_notes',
        ] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                $this->error("Required table {$tableName} does not exist.");

                return self::FAILURE;
            }
        }

        $rows = DB::table('rec_dg_member_feedback_journals')->orderBy('id')->get();
        if ($this->option('dry-run')) {
            $this->info('Rows ready to migrate: ' . $rows->count());

            return self::SUCCESS;
        }

        $groupIdsByPublicId = $this->groupIdsByPublicId();
        $personIdsByPublicId = $this->personIdsByPublicId();
        $questionMeta = $this->questionMetadata();

        $migrated = 0;
        DB::transaction(function () use ($rows, $groupIdsByPublicId, $personIdsByPublicId, $questionMeta, &$migrated): void {
            foreach ($rows as $row) {
                $publicId = trim((string) ($row->record_uid ?? ''));
                if ($publicId === '') {
                    $publicId = 'dg_member_feedback_' . (string) $row->id;
                }

                $createdAt = $this->timestampFrom([
                    $row->record_created_at ?? null,
                    $row->created_at ?? null,
                ]);
                $updatedAt = $this->timestampFrom([
                    $row->record_updated_at ?? null,
                    $row->updated_at ?? null,
                    $createdAt,
                ]);

                DB::table('discipleship_member_feedback_journals')->updateOrInsert(
                    ['public_id' => $publicId],
                    [
                        'branch_code' => $this->text($row->branch_code ?? $row->branch ?? 'kutisari', 'kutisari'),
                        'feedback_session' => $this->feedbackSession($row->feedback_session ?? null),
                        'discipleship_group_id' => $this->mappedId($groupIdsByPublicId, $row->group_id ?? null),
                        'leader_person_id' => $this->mappedId($personIdsByPublicId, $row->leader_id ?? null),
                        'respondent_person_id' => $this->mappedId($personIdsByPublicId, $row->respondent_person_id ?? null),
                        'respondent_name_snapshot' => $this->nullableText($row->respondent_name ?? null),
                        'leader_name_snapshot' => $this->nullableText($row->leader_name ?? null),
                        'group_name_snapshot' => $this->nullableText($row->group_name ?? null),
                        'group_label_snapshot' => $this->nullableText($row->group_label ?? null),
                        'group_progress_snapshot' => $this->nullableText($row->group_progress ?? null),
                        'source' => $this->text($row->source ?? 'public_form', 'public_form'),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ],
                );

                $journalId = (int) DB::table('discipleship_member_feedback_journals')
                    ->where('public_id', $publicId)
                    ->value('id');

                DB::table('discipleship_member_feedback_ratings')
                    ->where('discipleship_member_feedback_journal_id', $journalId)
                    ->delete();
                DB::table('discipleship_member_feedback_notes')
                    ->where('discipleship_member_feedback_journal_id', $journalId)
                    ->delete();

                $ratingRows = [];
                foreach ($this->jsonObject($row->ratings_json ?? null) as $questionKey => $score) {
                    $questionKey = trim((string) $questionKey);
                    $meta = $questionMeta['ratings'][$questionKey] ?? null;
                    $score = is_numeric($score) ? (int) $score : 0;
                    $scale = is_array($meta) ? (int) ($meta['scale'] ?? 10) : 10;
                    if ($questionKey === '' || $score < 1 || $score > $scale) {
                        continue;
                    }

                    $ratingRows[] = [
                        'discipleship_member_feedback_journal_id' => $journalId,
                        'section_key' => is_array($meta) ? $this->nullableText($meta['section_key'] ?? null) : null,
                        'question_key' => $questionKey,
                        'score' => $score,
                        'scale' => $scale,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ];
                }
                if ($ratingRows !== []) {
                    DB::table('discipleship_member_feedback_ratings')->insert($ratingRows);
                }

                $noteRows = [];
                foreach ($this->jsonObject($row->notes_json ?? null) as $noteKey => $content) {
                    $noteKey = trim((string) $noteKey);
                    $meta = $questionMeta['notes'][$noteKey] ?? null;
                    if ($noteKey === '') {
                        continue;
                    }

                    $noteRows[] = [
                        'discipleship_member_feedback_journal_id' => $journalId,
                        'section_key' => is_array($meta) ? $this->nullableText($meta['section_key'] ?? null) : null,
                        'note_key' => $noteKey,
                        'content' => $this->nullableText($content),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ];
                }
                if ($noteRows !== []) {
                    DB::table('discipleship_member_feedback_notes')->insert($noteRows);
                }

                $migrated++;
            }
        });

        $this->info("Migrated {$migrated} member feedback journal rows.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function groupIdsByPublicId(): array
    {
        if (! Schema::hasTable('rec_discipleship_groups')) {
            return [];
        }

        $map = [];
        foreach (DB::table('rec_discipleship_groups')->select(['id', 'record_uid'])->get() as $row) {
            $publicId = trim((string) ($row->record_uid ?? ''));
            if ($publicId !== '') {
                $map[$publicId] = (int) $row->id;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function personIdsByPublicId(): array
    {
        if (! Schema::hasTable('rec_people_registry')) {
            return [];
        }

        $map = [];
        $rows = DB::table('rec_people_registry')
            ->select(['id', 'record_uid', 'dg_person_id', 'legacy_dg_person_id'])
            ->get();

        foreach ($rows as $row) {
            foreach (['record_uid', 'dg_person_id', 'legacy_dg_person_id'] as $column) {
                $publicId = trim((string) ($row->{$column} ?? ''));
                if ($publicId !== '') {
                    $map[$publicId] = (int) $row->id;
                }
            }
        }

        return $map;
    }

    /**
     * @return array{ratings: array<string, array{section_key: string, scale: int}>, notes: array<string, array{section_key: string}>}
     */
    private function questionMetadata(): array
    {
        if (! function_exists('public_member_feedback_questions')) {
            require_once app_path('RecRuntime/support/public_member_feedback_questions.php');
        }

        $metadata = ['ratings' => [], 'notes' => []];
        foreach (public_member_feedback_questions() as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach (($section['ratings'] ?? []) as $rating) {
                if (! is_array($rating)) {
                    continue;
                }

                $questionKey = trim((string) ($rating['key'] ?? ''));
                if ($questionKey === '') {
                    continue;
                }

                $metadata['ratings'][$questionKey] = [
                    'section_key' => (string) $sectionKey,
                    'scale' => max(1, (int) ($rating['scale'] ?? 10)),
                ];
            }

            $noteKey = trim((string) ($section['note_key'] ?? ''));
            if ($noteKey !== '') {
                $metadata['notes'][$noteKey] = ['section_key' => (string) $sectionKey];
            }
        }

        return $metadata;
    }

    /**
     * @param array<int|string, mixed> $map
     */
    private function mappedId(array $map, mixed $publicId): ?int
    {
        $publicId = trim((string) $publicId);

        return $publicId === '' || ! isset($map[$publicId]) ? null : (int) $map[$publicId];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(mixed $payload): array
    {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, mixed> $candidates
     */
    private function timestampFrom(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate instanceof CarbonImmutable) {
                return $candidate->format('Y-m-d H:i:s');
            }

            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
            } catch (Throwable) {
                continue;
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

    private function feedbackSession(mixed $value): int
    {
        $value = (int) trim((string) $value);

        return in_array($value, [3, 12], true) ? $value : 3;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function text(mixed $value, string $default): string
    {
        $text = trim((string) $value);

        return $text === '' ? $default : $text;
    }
}
