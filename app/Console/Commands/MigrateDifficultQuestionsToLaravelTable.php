<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MigrateDifficultQuestionsToLaravelTable extends Command
{
    protected $signature = 'rec:migrate-difficult-questions {--dry-run : Count rows without writing}';

    protected $description = 'Migrate difficult question records from rec_difficult_questions to difficult_questions.';

    public function handle(): int
    {
        if (! Schema::hasTable('rec_difficult_questions')) {
            $this->warn('Source table rec_difficult_questions does not exist.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('difficult_questions')) {
            $this->error('Target table difficult_questions does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $rows = DB::table('rec_difficult_questions')->orderBy('id')->get();
        if ($this->option('dry-run')) {
            $this->info('Rows ready to migrate: ' . $rows->count());

            return self::SUCCESS;
        }

        $migrated = 0;
        foreach ($rows as $row) {
            $publicId = trim((string) ($row->record_uid ?? ''));
            if ($publicId === '') {
                $publicId = 'legacy_dq_' . (string) $row->id;
            }

            $createdAt = $this->timestampFrom([
                $row->created_at_legacy ?? null,
                $row->created_at ?? null,
            ]);
            $updatedAt = $this->timestampFrom([
                $row->record_updated_at ?? null,
                $row->updated_at ?? null,
                $createdAt,
            ]);

            DB::table('difficult_questions')->updateOrInsert(
                ['public_id' => $publicId],
                [
                    'asker_name' => $this->nullableText($row->asker_name ?? null),
                    'question' => (string) ($row->question ?? ''),
                    'password_hash' => $this->nullableText($row->password_hash ?? null),
                    'password_lookup_hash' => (string) ($row->password_lookup ?? ''),
                    'status' => $this->statusValue((string) ($row->status ?? 'pending')),
                    'answer' => $this->nullableText($row->answer ?? null),
                    'answered_by_username' => $this->nullableText($row->answered_by ?? null),
                    'answered_at' => $this->timestampFrom([$row->answered_at_legacy ?? null], null),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ],
            );
            $migrated++;
        }

        $this->info("Migrated {$migrated} difficult question rows.");

        return self::SUCCESS;
    }

    /**
     * @param array<int, mixed> $candidates
     */
    private function timestampFrom(array $candidates, ?string $default = 'now'): ?string
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

        return $default === 'now' ? now()->format('Y-m-d H:i:s') : $default;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function statusValue(string $status): string
    {
        $status = strtolower(trim($status));

        return $status === 'answered' ? 'answered' : 'pending';
    }
}
